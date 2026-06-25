<?php

namespace App\Http\Controllers;

use App\Models\Pick;
use App\Models\Pool;
use App\Models\User;
use App\Services\PickResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PickImportController extends Controller
{
    private const ROUND_ORDER = ['R32', 'R16', 'QF', 'SF', 'THIRD', 'FINAL'];

    private const ROUND_LABELS = [
        'R32' => 'Round of 32',
        'R16' => 'Round of 16',
        'QF' => 'Quarterfinals',
        'SF' => 'Semifinals',
        'THIRD' => 'Third Place',
        'FINAL' => 'Final',
    ];

    /**
     * Manager: the import landing page (download template + upload form).
     */
    public function showImport(Pool $pool): View
    {
        Gate::authorize('manage', $pool);
        abort_unless($pool->teams()->count() === 32, 404, 'This pool has no bracket yet.');

        return view('pools.picks.import', compact('pool'));
    }

    /**
     * Manager: download a per-pool Excel pick template.
     */
    public function template(Pool $pool): StreamedResponse
    {
        Gate::authorize('manage', $pool);
        abort_unless($pool->teams()->count() === 32, 404, 'This pool has no bracket yet.');

        $teams = $pool->teams()->pluck('name', 'id');

        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Picks');

        $sheet->setCellValue('A1', 'World Cup Pool — Pick Sheet: ' . $pool->name);
        $sheet->setCellValue('A2', 'Player email');
        $sheet->setCellValue('A3', 'Pick a winner (dropdown) for each match, top to bottom. Later rounds fill in automatically. Then enter the Final score at the bottom.');

        $sheet->fromArray(['Round', 'Match', 'Team A', 'Team B', 'Predicted Winner'], null, 'A5');

        // Fixed rows per match so the cascading formulas line up.
        $rowFor = fn (string $round, int $pos): int => match ($round) {
            'R32' => 5 + $pos,    // 6..21
            'R16' => 21 + $pos,   // 22..29
            'QF' => 29 + $pos,    // 30..33
            'SF' => 33 + $pos,    // 34..35
            'THIRD' => 36,
            'FINAL' => 37,
        };

        foreach ($this->orderedMatches($pool) as $m) {
            $row = $rowFor($m->round, $m->position);
            $sheet->setCellValue("A{$row}", self::ROUND_LABELS[$m->round]);
            $sheet->setCellValue("B{$row}", $m->position);

            // Team A / Team B: static for R32; formulas that pull the feeding
            // matches' winners for later rounds (so picks cascade forward).
            if ($m->round === 'R32') {
                $sheet->setCellValue("C{$row}", $teams[$m->team_a_id] ?? '');
                $sheet->setCellValue("D{$row}", $teams[$m->team_b_id] ?? '');
            } elseif (in_array($m->round, ['R16', 'QF', 'SF'], true)) {
                $childRound = ['R16' => 'R32', 'QF' => 'R16', 'SF' => 'QF'][$m->round];
                $aRow = $rowFor($childRound, 2 * $m->position - 1);
                $bRow = $rowFor($childRound, 2 * $m->position);
                $sheet->setCellValue("C{$row}", "=E{$aRow}");
                $sheet->setCellValue("D{$row}", "=E{$bRow}");
            } elseif ($m->round === 'THIRD') {
                // The two SF losers (winner row's other team).
                $sheet->setCellValue("C{$row}", '=IF($E$34="","",IF($E$34=$C$34,$D$34,$C$34))');
                $sheet->setCellValue("D{$row}", '=IF($E$35="","",IF($E$35=$C$35,$D$35,$C$35))');
            } elseif ($m->round === 'FINAL') {
                // The two SF winners.
                $sheet->setCellValue("C{$row}", '=E34');
                $sheet->setCellValue("D{$row}", '=E35');
            }

            // Winner dropdown limited to this match's two teams.
            $dv = $sheet->getCell("E{$row}")->getDataValidation();
            $dv->setType(DataValidation::TYPE_LIST);
            $dv->setAllowBlank(true);
            $dv->setShowDropDown(true);
            $dv->setShowErrorMessage(true);
            $dv->setErrorTitle('Invalid pick');
            $dv->setError('Pick one of the two teams shown in this match.');
            $dv->setFormula1("\$C\${$row}:\$D\${$row}");
        }

        $sheet->setCellValue('A39', 'Champion goals');
        $sheet->setCellValue('A40', 'Runner-up goals');

        // A little formatting.
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A5:E5')->getFont()->setBold(true);
        foreach (['A' => 16, 'B' => 8, 'C' => 22, 'D' => 22, 'E' => 24] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Reference tab with the exact team names.
        $teamsSheet = $ss->createSheet();
        $teamsSheet->setTitle('Teams');
        $teamsSheet->setCellValue('A1', 'Teams (use these exact names)');
        $r = 2;
        foreach ($teams as $name) {
            $teamsSheet->setCellValue('A' . $r, $name);
            $r++;
        }

        $filename = 'picks-template-pool-' . $pool->id . '.xlsx';

        return response()->streamDownload(function () use ($ss) {
            (new XlsxWriter($ss))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Manager: import one player's picks from an uploaded .xlsx or .csv file.
     */
    public function import(Request $request, Pool $pool): RedirectResponse
    {
        Gate::authorize('manage', $pool);
        abort_unless($pool->teams()->count() === 32, 404);

        $request->validate(['file' => ['required', 'file', 'max:2048']]);

        if (! $pool->picksOpen()) {
            return back()->with('error', 'The pool must be open for picks to import.');
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'csv'], true)) {
            return back()->withErrors(['file' => 'Upload an .xlsx or .csv file.']);
        }

        try {
            $reader = $ext === 'csv' ? new CsvReader() : IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $rows = $reader->load($file->getRealPath())->getSheet(0)->toArray(null, true, true, true);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Could not read the file. Make sure it matches the template.']);
        }

        $errors = [];

        // Lookups.
        $teamMap = [];
        foreach ($pool->teams as $t) {
            $teamMap[mb_strtolower(trim($t->name))] = $t->id;
            foreach (($t->aliases ?? []) as $alias) {
                $teamMap[mb_strtolower(trim($alias))] = $t->id;
            }
        }
        $matchMap = [];
        foreach ($pool->matches as $m) {
            $matchMap[$m->round . '-' . $m->position] = $m->id;
        }
        $labelToToken = [];
        foreach (self::ROUND_LABELS as $token => $label) {
            $labelToToken[mb_strtolower($label)] = $token;
            $labelToToken[mb_strtolower($token)] = $token;
        }

        // Parse rows.
        $email = null;
        $picks = [];
        $champGoals = null;
        $runnerGoals = null;
        foreach ($rows as $cells) {
            $a = mb_strtolower(trim((string) ($cells['A'] ?? '')));

            if ($a === 'player email') {
                $email = trim((string) ($cells['B'] ?? ''));
                continue;
            }
            if ($a === 'champion goals') {
                $champGoals = trim((string) ($cells['B'] ?? ''));
                continue;
            }
            if ($a === 'runner-up goals') {
                $runnerGoals = trim((string) ($cells['B'] ?? ''));
                continue;
            }

            $token = $labelToToken[$a] ?? null;
            if (! $token) {
                continue; // not a pick row
            }

            $matchId = $matchMap[$token . '-' . (int) trim((string) ($cells['B'] ?? ''))] ?? null;
            $winnerName = trim((string) ($cells['E'] ?? ''));
            if (! $matchId || $winnerName === '') {
                continue;
            }

            $teamId = $teamMap[mb_strtolower($winnerName)] ?? null;
            if (! $teamId) {
                $errors[] = "Unknown team \"{$winnerName}\".";

                continue;
            }
            $picks[$matchId] = $teamId;
        }

        // Validate.
        if (! $email) {
            $errors[] = 'Missing the "Player email" value.';
        }

        $resolver = new PickResolver($pool->matches);
        if (empty($errors) && ! $resolver->isCompleteAndConsistent($picks)) {
            $errors[] = 'The bracket is incomplete or inconsistent — every match needs a winner drawn from earlier-round picks.';
        }

        if (! is_numeric($champGoals) || ! is_numeric($runnerGoals)) {
            $errors[] = 'Enter both Champion goals and Runner-up goals as numbers.';
        } elseif ((int) $champGoals <= (int) $runnerGoals) {
            $errors[] = 'Champion goals must be greater than Runner-up goals (no ties).';
        }

        $member = null;
        if ($email) {
            $user = User::where('email', mb_strtolower($email))->first();
            $member = $user ? $pool->memberships()->where('user_id', $user->id)->first() : null;
            if (! $member) {
                $errors[] = "\"{$email}\" is not a member of this pool — invite them first.";
            }
        }

        if (! empty($errors)) {
            return back()->with('import_errors', array_values(array_unique($errors)));
        }

        DB::transaction(function () use ($pool, $member, $picks, $champGoals, $runnerGoals) {
            $pool->picks()->where('user_id', $member->user_id)->delete();
            $now = now();
            $insert = [];
            foreach ($picks as $matchId => $teamId) {
                $insert[] = [
                    'pool_id' => $pool->id,
                    'user_id' => $member->user_id,
                    'bracket_match_id' => $matchId,
                    'predicted_winner_team_id' => $teamId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            Pick::insert($insert);

            $member->update([
                'final_score_a' => (int) $champGoals,
                'final_score_b' => (int) $runnerGoals,
                'picks_submitted_at' => $now,
            ]);
        });

        return back()->with('status', "Imported picks for {$email}.");
    }

    /**
     * @return array<int, \App\Models\BracketMatch>
     */
    private function orderedMatches(Pool $pool): array
    {
        $idx = array_flip(self::ROUND_ORDER);
        $matches = $pool->matches()->get()->all();
        usort($matches, fn ($a, $b) => [$idx[$a->round], $a->position] <=> [$idx[$b->round], $b->position]);

        return $matches;
    }
}
