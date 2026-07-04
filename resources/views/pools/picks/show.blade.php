<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $isSelf ?? false ? 'My Picks' : $player->name . "'s Picks" }} — {{ $pool->name }}
            </h2>
            <a href="{{ route('pools.standings', $pool) }}" class="text-sm text-gray-600 underline">Back to standings</a>
        </div>
    </x-slot>

    @php($roundLabels = ['R32' => 'Round of 32', 'R16' => 'Round of 16', 'QF' => 'Quarterfinals', 'SF' => 'Semifinals', 'THIRD' => 'Third Place Match', 'FINAL' => 'Final'])

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-600">
                Score: <strong class="text-gray-900">{{ $membership->score }}</strong>
                · Correct picks: <strong class="text-gray-900">{{ $membership->correct_picks }}</strong>
                @if ($showFinalScore)
                    · Final score pick:
                    <strong class="text-gray-900">
                        {{ ($membership->final_score_a !== null && $membership->final_score_b !== null)
                            ? $membership->final_score_a . '–' . $membership->final_score_b
                            : '—' }}
                    </strong>
                @endif
            </div>

            @if ($picks->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-500">
                    This player hasn't submitted picks yet.
                </div>
            @elseif (empty($roundOrder))
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-500">
                    This player's picks aren't visible yet — they're revealed round by round as each round locks.
                </div>
            @else
                @foreach ($roundOrder as $round)
                    @php($roundMatches = $matches->get($round))
                    @continue(! $roundMatches)
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800 mb-3">{{ $roundLabels[$round] }}</h3>
                        <ul class="space-y-1 text-sm">
                            @foreach ($roundMatches as $m)
                                @php($pickedId = $picks[$m->id] ?? null)
                                @php($decided = $m->actual_winner_team_id !== null)
                                @php($correct = $decided && $pickedId === $m->actual_winner_team_id)
                                <li class="flex flex-wrap items-center gap-2 py-1">
                                    <span class="text-gray-400 w-6 text-right">{{ $m->position }}.</span>
                                    @if ($m->teamA && $m->teamB)
                                        <span class="text-gray-600"><x-flag :code="$m->teamA->country_code" />{{ $m->teamA->name }} vs <x-flag :code="$m->teamB->country_code" />{{ $m->teamB->name }}</span>
                                        <span class="text-gray-300">·</span>
                                    @endif
                                    <span class="font-medium {{ $decided ? ($correct ? 'text-green-700' : 'text-red-600') : 'text-gray-800' }}">
                                        @if ($pickedId)<x-flag :code="$teamCodes[$pickedId] ?? null" />{{ $teams[$pickedId] ?? '—' }}@else — @endif
                                    </span>
                                    @if ($decided)
                                        @if ($correct)
                                            <span class="text-green-600">✓</span>
                                        @else
                                            <span class="text-red-500">✗ (won: {{ $m->actualWinner?->name }})</span>
                                        @endif
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if ($round === 'FINAL' && $showFinalScore && $membership->final_score_a !== null && $membership->final_score_b !== null)
                            @php($f = $roundMatches->first())
                            <p class="mt-2 text-sm text-gray-600">
                                Predicted Final score:
                                <span class="font-medium"><x-flag :code="$f->teamA?->country_code" />{{ $f->teamA?->name }} {{ $membership->final_score_a }} – {{ $membership->final_score_b }} <x-flag :code="$f->teamB?->country_code" />{{ $f->teamB?->name }}</span>
                            </p>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
