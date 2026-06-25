<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $pool->name }} — My Picks (Incremental)
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 bg-green-100 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">{{ $errors->first() }}</div>
            @endif

            <div class="px-4 py-3 bg-blue-50 text-blue-800 rounded-md text-sm">
                You pick one round at a time. A new round opens after the manager enters the previous round's results.
            </div>

            @foreach (\App\Models\Pool::ROUND_SEQUENCE as $round)
                @php($r = $rounds[$round])
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-800">{{ $r['label'] }}</h3>
                        @if ($r['complete'])
                            <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">finished</span>
                        @elseif ($r['locked'])
                            <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">locked</span>
                        @elseif ($r['open'])
                            <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded-full bg-green-50 text-green-700">open</span>
                        @else
                            <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded-full bg-gray-100 text-gray-400">upcoming</span>
                        @endif
                    </div>

                    @if ($r['open'])
                        {{-- Editable: pick winners for this round --}}
                        <form method="POST" action="{{ route('pools.picks.round', $pool) }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="round" value="{{ $round }}">

                            @foreach ($r['matches'] as $m)
                                @php($sel = (int) old('winners.' . $m->id, $picks[$m->id] ?? 0))
                                <div class="flex flex-wrap items-center gap-3 text-sm border-b last:border-0 pb-2">
                                    <span class="text-gray-400 w-6">{{ $m->position }}.</span>
                                    <label class="inline-flex items-center gap-2">
                                        <input type="radio" name="winners[{{ $m->id }}]" value="{{ $m->team_a_id }}"
                                               @checked($sel === $m->team_a_id)>
                                        <x-flag :code="$m->teamA?->country_code" />{{ $m->teamA?->name }}
                                    </label>
                                    <label class="inline-flex items-center gap-2">
                                        <input type="radio" name="winners[{{ $m->id }}]" value="{{ $m->team_b_id }}"
                                               @checked($sel === $m->team_b_id)>
                                        <x-flag :code="$m->teamB?->country_code" />{{ $m->teamB?->name }}
                                    </label>
                                </div>
                            @endforeach

                            <x-input-error :messages="$errors->get('picks')" class="mt-1" />

                            @if ($round === 'FINAL')
                                @php($f = $r['matches']->first())
                                <div class="pt-2">
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="text-gray-600">Final score (tie-breaker):</span>
                                        <span class="font-medium"><x-flag :code="$f->teamA?->country_code" />{{ $f->teamA?->name }}</span>
                                        <input type="number" name="final_score_a" min="0" max="99"
                                               value="{{ old('final_score_a', $finalScoreA) }}"
                                               class="w-16 text-sm border-gray-300 rounded-md">
                                        <span class="text-gray-400">–</span>
                                        <input type="number" name="final_score_b" min="0" max="99"
                                               value="{{ old('final_score_b', $finalScoreB) }}"
                                               class="w-16 text-sm border-gray-300 rounded-md">
                                        <span class="font-medium"><x-flag :code="$f->teamB?->country_code" />{{ $f->teamB?->name }}</span>
                                    </div>
                                    <x-input-error :messages="$errors->get('final_score')" class="mt-1" />
                                </div>
                            @endif

                            <x-primary-button>{{ __('Save ' . $r['label']) }}</x-primary-button>
                        </form>
                    @elseif ($r['locked'] || $r['complete'])
                        {{-- Locked or finished: show this player's picks read-only --}}
                        <ul class="space-y-1 text-sm">
                            @foreach ($r['matches'] as $m)
                                @php($picked = $picks[$m->id] ?? null)
                                @php($decided = $m->actual_winner_team_id !== null)
                                @php($correct = $decided && $picked === $m->actual_winner_team_id)
                                <li class="flex flex-wrap items-center gap-2 py-1">
                                    <span class="text-gray-400 w-6 text-right">{{ $m->position }}.</span>
                                    <span class="text-gray-600"><x-flag :code="$m->teamA?->country_code" />{{ $m->teamA?->name }} vs <x-flag :code="$m->teamB?->country_code" />{{ $m->teamB?->name }}</span>
                                    <span class="text-gray-300">·</span>
                                    <span class="font-medium {{ $decided ? ($correct ? 'text-green-700' : 'text-red-600') : 'text-gray-800' }}">
                                        @if ($picked)<x-flag :code="$teamCodes[$picked] ?? null" />{{ $teams[$picked] ?? '—' }}@else no pick @endif
                                    </span>
                                    @if ($decided)
                                        {!! $correct ? '<span class="text-green-600">✓</span>' : '<span class="text-red-500">✗</span>' !!}
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if ($round === 'FINAL' && $finalScoreA !== null && $finalScoreB !== null)
                            @php($f = $r['matches']->first())
                            <p class="mt-2 text-sm text-gray-600">
                                Your predicted Final score:
                                <span class="font-medium"><x-flag :code="$f->teamA?->country_code" />{{ $f->teamA?->name }} {{ $finalScoreA }} – {{ $finalScoreB }} <x-flag :code="$f->teamB?->country_code" />{{ $f->teamB?->name }}</span>
                            </p>
                        @endif
                    @else
                        <p class="text-sm text-gray-400 italic">
                            @if ($round === 'R32')
                                Opens when the manager opens the pool for picks.
                            @else
                                Opens after the {{ $labels[$feeders[$round] ?? ''] ?? 'previous round' }} results are in.
                            @endif
                        </p>
                    @endif
                </div>
            @endforeach

            <a href="{{ route('pools.show', $pool) }}" class="text-sm text-gray-600 underline">Back to pool</a>
        </div>
    </div>
</x-app-layout>
