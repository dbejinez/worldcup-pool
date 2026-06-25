<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use App\Services\PickResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PickImportTest extends TestCase
{
    use RefreshDatabase;

    private const ROUND_LABELS = [
        'R32' => 'Round of 32', 'R16' => 'Round of 16', 'QF' => 'Quarterfinals',
        'SF' => 'Semifinals', 'THIRD' => 'Third Place', 'FINAL' => 'Final',
    ];

    /** @return array<int, array{a: string, b: string}> */
    private function sampleMatchups(): array
    {
        $m = [];
        for ($i = 0; $i < 16; $i++) {
            $m[] = ['a' => 'Team ' . ($i * 2 + 1), 'b' => 'Team ' . ($i * 2 + 2)];
        }

        return $m;
    }

    private function openPoolWithBracket(User $manager): Pool
    {
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'WC Pool']);
        $pool = Pool::where('name', 'WC Pool')->first();
        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $this->sampleMatchups()]);
        $pool->update(['deadline_utc' => now()->addDays(2), 'status' => 'open']);

        return $pool->fresh();
    }

    /**
     * Build a valid CSV (consistent slot-A picks). $badName replaces one winner with a bogus team.
     */
    private function buildCsv(Pool $pool, string $email, int $champ = 2, int $runner = 1, ?string $badName = null): string
    {
        $matches = $pool->matches()->get();
        $resolver = new PickResolver($matches);
        $teams = $pool->teams()->pluck('name', 'id');

        $picks = [];
        $order = ['R32', 'R16', 'QF', 'SF', 'THIRD', 'FINAL'];
        foreach ($order as $round) {
            foreach ($matches->where('round', $round) as $m) {
                [$a] = $resolver->participants($m->id, $picks);
                $picks[$m->id] = $a;
            }
        }

        $lines = [];
        $lines[] = 'Player email,' . $email;
        $lines[] = '';
        $lines[] = 'Round,Match,Team A,Team B,Predicted Winner';

        $replaced = false;
        foreach ($order as $round) {
            foreach ($matches->where('round', $round)->sortBy('position') as $m) {
                $name = $teams[$picks[$m->id]];
                if ($badName !== null && ! $replaced) {
                    $name = $badName;
                    $replaced = true;
                }
                $lines[] = self::ROUND_LABELS[$round] . ',' . $m->position . ',,,' . $name;
            }
        }

        $lines[] = '';
        $lines[] = 'Champion goals,' . $champ;
        $lines[] = 'Runner-up goals,' . $runner;

        return implode("\n", $lines);
    }

    private function member(Pool $pool, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $pool->memberships()->create(['user_id' => $user->id, 'role' => 'player', 'joined_at' => now()]);

        return $user;
    }

    public function test_manager_can_import_picks_from_csv(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->member($pool, 'p@example.com');

        $csv = $this->buildCsv($pool, 'p@example.com');
        $file = UploadedFile::fake()->createWithContent('picks.csv', $csv);

        $this->actingAs($manager)
            ->post(route('pools.picks.import', $pool), ['file' => $file])
            ->assertSessionHas('status');

        $this->assertSame(32, $pool->picks()->where('user_id', $player->id)->count());

        $membership = $pool->memberships()->where('user_id', $player->id)->first();
        $this->assertSame(2, $membership->final_score_a);
        $this->assertSame(1, $membership->final_score_b);
        $this->assertNotNull($membership->picks_submitted_at);
    }

    public function test_import_rejects_unknown_team(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->member($pool, 'p@example.com');

        $csv = $this->buildCsv($pool, 'p@example.com', badName: 'Nowhere United');
        $file = UploadedFile::fake()->createWithContent('picks.csv', $csv);

        $this->actingAs($manager)
            ->post(route('pools.picks.import', $pool), ['file' => $file])
            ->assertSessionHas('import_errors');

        $this->assertSame(0, $pool->picks()->where('user_id', $player->id)->count());
    }

    public function test_import_rejects_non_member_email(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        User::factory()->create(['email' => 'stranger@example.com']); // exists but not a member

        $csv = $this->buildCsv($pool, 'stranger@example.com');
        $file = UploadedFile::fake()->createWithContent('picks.csv', $csv);

        $this->actingAs($manager)
            ->post(route('pools.picks.import', $pool), ['file' => $file])
            ->assertSessionHas('import_errors');

        $this->assertSame(0, $pool->picks()->count());
    }

    public function test_player_cannot_import(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);
        $player = $this->member($pool, 'p@example.com');

        $file = UploadedFile::fake()->createWithContent('picks.csv', 'Player email,p@example.com');

        $this->actingAs($player)
            ->post(route('pools.picks.import', $pool), ['file' => $file])
            ->assertForbidden();
    }

    public function test_manager_can_download_template(): void
    {
        $manager = User::factory()->create();
        $pool = $this->openPoolWithBracket($manager);

        $response = $this->actingAs($manager)
            ->get(route('pools.picks.template', $pool))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Load the generated file back and confirm the cascade formulas exist.
        $tmp = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getSheetByName('Picks');

        // R16 match 1, Team A = winner of R32 match 1 (row 6).
        $this->assertSame('=E6', $sheet->getCell('C22')->getValue());
        $this->assertSame('Champion goals', $sheet->getCell('A39')->getValue());

        @unlink($tmp);
    }
}
