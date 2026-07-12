<?php

namespace Tests\Feature;

use App\Models\Pick;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class BulkImportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: string, 1: string}[] */
    private function sampleMatchups(): array
    {
        $m = [];
        for ($i = 0; $i < 16; $i++) {
            $m[] = ['a' => 'Team ' . ($i * 2 + 1), 'b' => 'Team ' . ($i * 2 + 2)];
        }

        return $m;
    }

    /** @return array{0: User, 1: Pool} */
    private function createPoolWithBracket(): array
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'Bulk Pool']);
        $pool = Pool::where('name', 'Bulk Pool')->first();
        $pool->update(['approved_at' => now()]);

        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), [
            'matchups' => $this->sampleMatchups(),
        ]);

        return [$manager, $pool->fresh()];
    }

    /**
     * Build a real .xlsx file in the Slack/Forms export format.
     *
     * Each $players entry: ['email' => string, 'name' => string, 'picks' => [matchId => teamName]]
     */
    private function buildXlsx(Pool $pool, array $players, string $round = 'R32'): UploadedFile
    {
        $matches = $pool->matches()
            ->with(['teamA', 'teamB'])
            ->where('round', $round)
            ->orderBy('position')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header row: A–E metadata, F+ match columns ("TeamA vs TeamB")
        $sheet->setCellValue('A1', 'Id');
        $sheet->setCellValue('B1', 'Start time');
        $sheet->setCellValue('C1', 'Completion time');
        $sheet->setCellValue('D1', 'Email');
        $sheet->setCellValue('E1', 'Name');

        $colIdx = 6;
        foreach ($matches as $m) {
            $col = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue("{$col}1", "{$m->teamA->name} vs {$m->teamB->name}");
            $colIdx++;
        }

        // Data rows (row 2+)
        $rowIdx = 2;
        foreach ($players as $player) {
            $sheet->setCellValue("A{$rowIdx}", $rowIdx - 1);
            $sheet->setCellValue("B{$rowIdx}", 46202.0);
            $sheet->setCellValue("C{$rowIdx}", 46202.5);  // Excel serial ~2026
            $sheet->setCellValue("D{$rowIdx}", $player['email']);
            $sheet->setCellValue("E{$rowIdx}", $player['name']);

            $colIdx = 6;
            foreach ($matches as $m) {
                if (isset($player['picks'][$m->id])) {
                    $col = Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->setCellValue("{$col}{$rowIdx}", $player['picks'][$m->id]);
                }
                $colIdx++;
            }
            $rowIdx++;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'bulk_test_') . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($tmp);

        return new UploadedFile($tmp, 'picks.xlsx', null, null, true);
    }

    /** All round matches picked with their team A (slot A always wins). */
    private function allTeamAPicks(Pool $pool, string $round = 'R32'): array
    {
        $picks = [];
        foreach ($pool->matches()->with('teamA')->where('round', $round)->get() as $m) {
            $picks[$m->id] = $m->teamA->name;
        }

        return $picks;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_manager_can_access_bulk_import_page(): void
    {
        [$manager, $pool] = $this->createPoolWithBracket();

        $this->actingAs($manager)
            ->get(route('pools.picks.bulk-import.show', $pool))
            ->assertOk();
    }

    public function test_player_cannot_access_bulk_import_page(): void
    {
        [$manager, $pool] = $this->createPoolWithBracket();
        $player = User::factory()->create();
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);

        $this->actingAs($player)
            ->get(route('pools.picks.bulk-import.show', $pool))
            ->assertForbidden();
    }

    public function test_valid_xlsx_renders_preview_with_player_and_round(): void
    {
        [$manager, $pool] = $this->createPoolWithBracket();
        $picks = $this->allTeamAPicks($pool);
        $file = $this->buildXlsx($pool, [
            ['email' => 'alice@example.com', 'name' => 'Alice Smith', 'picks' => $picks],
        ]);

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import.preview', $pool), ['file' => $file])
            ->assertOk()
            ->assertSee('alice@example.com')
            ->assertSee('Round of 32');
    }

    public function test_round_override_with_no_matching_columns_redirects_with_error(): void
    {
        [$manager, $pool] = $this->createPoolWithBracket();
        $file = $this->buildXlsx($pool, [
            ['email' => 'alice@example.com', 'name' => 'Alice', 'picks' => $this->allTeamAPicks($pool)],
        ]);

        // File has R32 columns; asking for R16 should find no R16 matches
        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import.preview', $pool), ['file' => $file, 'round' => 'R16'])
            ->assertRedirect(route('pools.picks.bulk-import.show', $pool))
            ->assertSessionHasErrors('file');
    }

    public function test_cancel_clears_session_and_does_not_write_to_db(): void
    {
        [$manager, $pool] = $this->createPoolWithBracket();
        $file = $this->buildXlsx($pool, [
            ['email' => 'alice@example.com', 'name' => 'Alice', 'picks' => $this->allTeamAPicks($pool)],
        ]);

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import.preview', $pool), ['file' => $file]);

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import.cancel', $pool))
            ->assertRedirect(route('pools.picks.bulk-import.show', $pool));

        $this->assertDatabaseCount('picks', 0);
    }

    public function test_confirm_import_creates_new_user_with_temp_password_and_must_change_flag(): void
    {
        [$manager, $pool] = $this->createPoolWithBracket();
        $file = $this->buildXlsx($pool, [
            ['email' => 'newplayer@example.com', 'name' => 'Sofia Chavez', 'picks' => $this->allTeamAPicks($pool)],
        ]);

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import.preview', $pool), ['file' => $file]);

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import', $pool))
            ->assertRedirect(route('pools.picks.bulk-import.show', $pool));

        $user = User::where('email', 'newplayer@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Sofia Chavez', $user->name);
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertTrue(Hash::check('Sofia123', $user->password));
    }

    public function test_confirm_import_does_not_overwrite_existing_user_name(): void
    {
        [$manager, $pool] = $this->createPoolWithBracket();
        User::factory()->create(['name' => 'John Smith', 'email' => 'john@example.com']);
        $file = $this->buildXlsx($pool, [
            ['email' => 'john@example.com', 'name' => 'Johnny Smith', 'picks' => $this->allTeamAPicks($pool)],
        ]);

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import.preview', $pool), ['file' => $file]);

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import', $pool));

        $this->assertSame('John Smith', User::where('email', 'john@example.com')->value('name'));
    }

    public function test_confirm_import_does_not_create_duplicate_membership(): void
    {
        [$manager, $pool] = $this->createPoolWithBracket();
        $player = User::factory()->create(['email' => 'alice@example.com']);
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);
        $file = $this->buildXlsx($pool, [
            ['email' => 'alice@example.com', 'name' => 'Alice', 'picks' => $this->allTeamAPicks($pool)],
        ]);

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import.preview', $pool), ['file' => $file]);

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import', $pool));

        $this->assertSame(
            1,
            $pool->memberships()->where('user_id', $player->id)->count()
        );
    }

    public function test_confirm_import_replaces_only_this_rounds_picks(): void
    {
        [$manager, $pool] = $this->createPoolWithBracket();
        $player = User::factory()->create(['email' => 'alice@example.com']);
        $pool->memberships()->create(['user_id' => $player->id, 'role' => 'player', 'joined_at' => now()]);

        // Insert a pick for a QF match (a different round)
        $qfMatch = $pool->matches()->where('round', 'QF')->first();
        $anyTeam = $pool->teams()->first();
        Pick::insert([
            'pool_id'                  => $pool->id,
            'user_id'                  => $player->id,
            'bracket_match_id'         => $qfMatch->id,
            'predicted_winner_team_id' => $anyTeam->id,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $file = $this->buildXlsx($pool, [
            ['email' => 'alice@example.com', 'name' => 'Alice', 'picks' => $this->allTeamAPicks($pool)],
        ]);

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import.preview', $pool), ['file' => $file]);

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import', $pool));

        // QF pick must still be there
        $this->assertDatabaseHas('picks', ['user_id' => $player->id, 'bracket_match_id' => $qfMatch->id]);

        // R32 picks must now exist (16 matches)
        $r32MatchIds = $pool->matches()->where('round', 'R32')->pluck('id');
        $this->assertSame(
            16,
            Pick::where('user_id', $player->id)->whereIn('bracket_match_id', $r32MatchIds)->count()
        );
    }

    public function test_confirm_import_without_session_payload_redirects_with_error(): void
    {
        [$manager, $pool] = $this->createPoolWithBracket();

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import', $pool))
            ->assertRedirect(route('pools.picks.bulk-import.show', $pool))
            ->assertSessionHasErrors('file');
    }

    public function test_xlsx_with_no_recognisable_match_headers_redirects_with_error(): void
    {
        [$manager, $pool] = $this->createPoolWithBracket();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Id');
        $sheet->setCellValue('B1', 'Start time');
        $sheet->setCellValue('C1', 'Completion time');
        $sheet->setCellValue('D1', 'Email');
        $sheet->setCellValue('E1', 'Name');
        $sheet->setCellValue('F1', 'Not a valid match header');
        $sheet->setCellValue('D2', 'alice@example.com');
        $sheet->setCellValue('E2', 'Alice');

        $tmp = tempnam(sys_get_temp_dir(), 'bad_test_') . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($tmp);

        $this->actingAs($manager)
            ->post(route('pools.picks.bulk-import.preview', $pool), [
                'file' => new UploadedFile($tmp, 'picks.xlsx', null, null, true),
            ])
            ->assertRedirect(route('pools.picks.bulk-import.show', $pool))
            ->assertSessionHasErrors('file');
    }
}
