<?php

namespace App\Http\Controllers;

use App\Models\Pick;
use App\Models\Pool;
use App\Models\User;
use App\Services\ResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BulkImportController extends Controller
{
    private const ROUND_ORDER = ['R32', 'R16', 'QF', 'SF', 'THIRD', 'FINAL'];

    public const ROUND_LABELS = [
        'R32'   => 'Round of 32',
        'R16'   => 'Round of 16',
        'QF'    => 'Quarterfinals',
        'SF'    => 'Semifinals',
        'THIRD' => 'Third Place Match',
        'FINAL' => 'Final',
    ];

    // Columns A–E in the Slack/Forms export are fixed metadata.
    private const METADATA_COLS = ['A', 'B', 'C', 'D', 'E'];

    // ── Public actions ────────────────────────────────────────────────────────

    public function show(Pool $pool): View
    {
        Gate::authorize('manage', $pool);
        abort_unless($pool->teams()->exists(), 404, 'This pool has no bracket yet.');

        $result = session()->pull('bulk_import.' . $pool->id . '.result');

        return view('pools.picks.bulk-import', compact('pool', 'result'));
    }

    /**
     * Parse the uploaded file (or re-use cached raw data for a round change)
     * and render a read-only preview for the manager to confirm or cancel.
     */
    public function preview(Request $request, Pool $pool): View|RedirectResponse
    {
        Gate::authorize('manage', $pool);
        abort_unless($pool->teams()->exists(), 404, 'This pool has no bracket yet.');

        $pool->load(['teams', 'matches']);
        $sessionKey    = 'bulk_import.' . $pool->id;
        $roundOverride = $request->input('round') ?: null; // e.g. 'THIRD', null = auto-detect

        // ── Step 1: get raw row data ──────────────────────────────────────
        if ($request->hasFile('file')) {
            $request->validate(['file' => [
                'required', 'file', 'max:4096',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream',
            ]]);

            $file = $request->file('file');
            if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
                return back()->withErrors(['file' => __('Upload an .xlsx file (the Microsoft Forms / Slack export).')]);
            }

            try {
                $reader = IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(true);
                $allRows = $reader->load($file->getRealPath())
                    ->getSheet(0)
                    ->toArray(null, true, true, true);
            } catch (\Throwable) {
                return back()->withErrors(['file' => __('Could not read the file. Make sure it is a valid .xlsx export.')]);
            }

            // Row keys are 1-based when returnCellRef = true.
            $headerRow = reset($allRows);
            $dataRows  = array_filter($allRows, fn ($k) => $k >= 2, ARRAY_FILTER_USE_KEY);

            session([
                $sessionKey . '.raw_headers' => $headerRow,
                $sessionKey . '.raw_data'    => array_values($dataRows),
            ]);
        } elseif (session()->has($sessionKey . '.raw_headers')) {
            // Manager changed the round on the upload page without re-uploading.
            $headerRow = session($sessionKey . '.raw_headers');
            $dataRows  = session($sessionKey . '.raw_data');
        } else {
            return redirect()->route('pools.picks.bulk-import.show', $pool)
                ->withErrors(['file' => __('Please upload a file first.')]);
        }

        // ── Step 2: map column headers to bracket matches ─────────────────
        $teamMap     = $this->buildTeamMap($pool);
        $matchByPair = $this->buildMatchByPair($pool);

        $colToMatch = [];
        foreach ($headerRow as $col => $header) {
            if (in_array($col, self::METADATA_COLS, true)) {
                continue;
            }
            $header = trim((string) $header);
            if (! str_contains($header, ' vs ')) {
                continue;
            }
            [$nameA, $nameB] = array_map('trim', explode(' vs ', $header, 2));
            $idA   = $teamMap[mb_strtolower($nameA)] ?? null;
            $idB   = $teamMap[mb_strtolower($nameB)] ?? null;
            if (! $idA || ! $idB) {
                continue;
            }
            $match = $matchByPair[$idA . ':' . $idB] ?? null;
            if (! $match) {
                continue;
            }
            $colToMatch[$col] = $match;
        }

        if (empty($colToMatch)) {
            return redirect()->route('pools.picks.bulk-import.show', $pool)
                ->withErrors(['file' => __('No valid match columns found. Check that team names in the file match the pool bracket.')]);
        }

        // ── Step 3: auto-detect or apply round override ───────────────────
        $detectedRounds = collect($colToMatch)->pluck('round')->unique()->values()->all();
        $detectedRound  = count($detectedRounds) === 1 ? $detectedRounds[0] : null;
        $selectedRound  = $roundOverride ?? $detectedRound;

        if (! $selectedRound) {
            return redirect()->route('pools.picks.bulk-import.show', $pool)
                ->withErrors(['file' => __('Could not auto-detect the round. Please select it manually.')]);
        }

        $roundMatches = array_filter($colToMatch, fn ($m) => $m->round === $selectedRound);

        if (empty($roundMatches)) {
            return redirect()->route('pools.picks.bulk-import.show', $pool)
                ->withErrors(['file' => __('No matches found for the selected round in this file.')]);
        }

        // ── Step 4: parse player rows ─────────────────────────────────────
        $matchIds = collect($roundMatches)->pluck('id')->all();
        $players  = [];

        foreach ($dataRows as $rowData) {
            $email = mb_strtolower(trim((string) ($rowData['D'] ?? '')));
            $name  = trim((string) ($rowData['E'] ?? ''));

            if (! $email || ! str_contains($email, '@')) {
                continue;
            }

            $completionSerial = $rowData['C'] ?? null;
            $submittedAt      = is_numeric($completionSerial)
                ? Carbon::createFromTimestamp(((float) $completionSerial - 25569) * 86400)->utc()
                : null;

            $user = User::where('email', $email)->first();

            $picks      = [];
            $blankCount = 0;
            $warnings   = [];

            foreach ($roundMatches as $col => $match) {
                $pickedName = trim((string) ($rowData[$col] ?? ''));
                if ($pickedName === '') {
                    $blankCount++;
                    continue;
                }
                $teamId = $teamMap[mb_strtolower($pickedName)] ?? null;
                if (! $teamId) {
                    $warnings[] = $pickedName;
                    continue;
                }
                $picks[$match->id] = $teamId;
            }

            $players[] = [
                'email'         => $email,
                'name'          => $name,
                'user_id'       => $user?->id,
                'exists'        => $user !== null,
                'picks'         => $picks,
                'blank_count'   => $blankCount,
                'total_matches' => count($roundMatches),
                'submitted_at'  => $submittedAt?->toIso8601String(),
                'warnings'      => $warnings,
            ];
        }

        if (empty($players)) {
            return redirect()->route('pools.picks.bulk-import.show', $pool)
                ->withErrors(['file' => __('No player rows found in the file.')]);
        }

        // ── Step 5: store payload and render preview ──────────────────────
        $payload = [
            'pool_id'        => $pool->id,
            'round'          => $selectedRound,
            'detected_round' => $detectedRound,
            'match_ids'      => $matchIds,
            'players'        => $players,
        ];

        session([$sessionKey . '.payload' => $payload]);

        $roundLabels = self::ROUND_LABELS;

        return view('pools.picks.bulk-import-preview',
            compact('pool', 'payload', 'selectedRound', 'detectedRound', 'roundLabels'));
    }

    /**
     * Commit the import from the session payload — create accounts, save picks,
     * recompute scores.
     */
    public function import(Request $request, Pool $pool): RedirectResponse
    {
        Gate::authorize('manage', $pool);

        $sessionKey = 'bulk_import.' . $pool->id;
        $payload    = session($sessionKey . '.payload');

        if (! $payload || $payload['pool_id'] !== $pool->id) {
            return redirect()->route('pools.picks.bulk-import.show', $pool)
                ->withErrors(['file' => __('Import session expired. Please upload the file again.')]);
        }

        $round    = $payload['round'];
        $matchIds = $payload['match_ids'];
        $players  = $payload['players'];
        $newUsers = [];

        DB::transaction(function () use ($pool, $matchIds, $players, &$newUsers) {
            foreach ($players as $p) {
                // Find or create the user account.
                if ($p['exists']) {
                    $user = User::find($p['user_id']);
                } else {
                    $firstName = Str::before($p['name'], ' ') ?: $p['name'];
                    $tempPwd   = $firstName . '123';
                    $user      = User::forceCreate([
                        'name'                 => $p['name'],
                        'email'                => $p['email'],
                        'email_verified_at'    => now(),
                        'password'             => Hash::make($tempPwd),
                        'must_change_password' => true,
                    ]);
                    $newUsers[$p['email']] = ['name' => $p['name'], 'password' => $tempPwd];
                }

                if (! $user) {
                    continue;
                }

                // Add to pool as player if not already a member.
                $pool->memberships()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['role' => 'player', 'joined_at' => now()]
                );

                // Replace this round's picks only.
                Pick::where('user_id', $user->id)
                    ->whereIn('bracket_match_id', $matchIds)
                    ->delete();

                if (! empty($p['picks'])) {
                    $now    = now();
                    $rows   = [];
                    foreach ($p['picks'] as $matchId => $teamId) {
                        $rows[] = [
                            'pool_id'                  => $pool->id,
                            'user_id'                  => $user->id,
                            'bracket_match_id'         => $matchId,
                            'predicted_winner_team_id' => $teamId,
                            'created_at'               => $now,
                            'updated_at'               => $now,
                        ];
                    }
                    Pick::insert($rows);
                }

                if ($p['submitted_at']) {
                    $pool->memberships()
                        ->where('user_id', $user->id)
                        ->update(['picks_submitted_at' => $p['submitted_at']]);
                }
            }
        });

        // Update standings to reflect the newly imported picks.
        (new ResultService)->recomputeScores($pool);

        session([
            $sessionKey . '.result' => [
                'imported'  => count($players),
                'new_users' => $newUsers,
                'round'     => __(self::ROUND_LABELS[$round] ?? $round),
            ],
        ]);

        session()->forget([
            $sessionKey . '.payload',
            $sessionKey . '.raw_headers',
            $sessionKey . '.raw_data',
        ]);

        return redirect()->route('pools.picks.bulk-import.show', $pool);
    }

    public function cancel(Pool $pool): RedirectResponse
    {
        Gate::authorize('manage', $pool);
        session()->forget('bulk_import.' . $pool->id);

        return redirect()->route('pools.picks.bulk-import.show', $pool);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildTeamMap(Pool $pool): array
    {
        $map = [];
        foreach ($pool->teams as $t) {
            $map[mb_strtolower(trim($t->name))] = $t->id;
            foreach (($t->aliases ?? []) as $alias) {
                $map[mb_strtolower(trim($alias))] = $t->id;
            }
        }

        return $map;
    }

    private function buildMatchByPair(Pool $pool): array
    {
        $map = [];
        foreach ($pool->matches as $m) {
            if ($m->team_a_id && $m->team_b_id) {
                $map[$m->team_a_id . ':' . $m->team_b_id] = $m;
                $map[$m->team_b_id . ':' . $m->team_a_id] = $m;
            }
        }

        return $map;
    }
}
