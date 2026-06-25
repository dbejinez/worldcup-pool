<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\User;
use App\Support\CountryFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_flags_maps_names_and_aliases(): void
    {
        $this->assertSame('mx', CountryFlags::codeFor('Mexico'));
        $this->assertSame('us', CountryFlags::codeFor('United States'));
        $this->assertSame('us', CountryFlags::codeFor('USA'));
        $this->assertSame('gb-eng', CountryFlags::codeFor('England'));
        $this->assertSame('tr', CountryFlags::codeFor('Türkiye'));
        $this->assertNull(CountryFlags::codeFor('Team 1'));
        $this->assertNull(CountryFlags::codeFor(null));
    }

    public function test_bracket_build_sets_team_country_codes(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('pools.store'), ['name' => 'Flags', 'method' => 'full']);
        $pool = Pool::first();

        $matchups = [['a' => 'Mexico', 'b' => 'Sweden']];
        for ($i = 1; $i < 16; $i++) {
            $matchups[] = ['a' => 'Team ' . ($i * 2 + 1), 'b' => 'Team ' . ($i * 2 + 2)];
        }

        $this->actingAs($manager)->post(route('pools.bracket.store', $pool), ['matchups' => $matchups]);

        $this->assertDatabaseHas('teams', ['pool_id' => $pool->id, 'name' => 'Mexico', 'country_code' => 'mx']);
        $this->assertDatabaseHas('teams', ['pool_id' => $pool->id, 'name' => 'Sweden', 'country_code' => 'se']);
        $this->assertDatabaseHas('teams', ['pool_id' => $pool->id, 'name' => 'Team 3', 'country_code' => null]);
    }
}
