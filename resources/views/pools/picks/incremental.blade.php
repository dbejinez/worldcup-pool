<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $pool->name }} — {{ __('My Picks') }}
        </h2>
    </x-slot>

    <style>[x-cloak]{display:none!important}</style>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 bg-green-100 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">{{ $errors->first() }}</div>
            @endif

            {{-- How-to strip --}}
            <div class="bg-white shadow-sm sm:rounded-lg px-6 py-4">
                <div class="grid grid-cols-3 divide-x divide-gray-100 text-center text-sm">
                    <div class="px-4 py-2">
                        <div class="text-2xl mb-1">1️⃣</div>
                        <div class="font-semibold text-gray-800">{{ __('1️⃣ Click a team') }}</div>
                        <div class="text-gray-500 text-xs mt-0.5">{{ __('to pick the winner of each match') }}</div>
                    </div>
                    <div class="px-4 py-2">
                        <div class="text-2xl mb-1">2️⃣</div>
                        <div class="font-semibold text-gray-800">{{ __('2️⃣ Save this round') }}</div>
                        <div class="text-gray-500 text-xs mt-0.5">{{ __('Pick all matches in the open round and hit Save.') }}</div>
                    </div>
                    <div class="px-4 py-2">
                        <div class="text-2xl mb-1">3️⃣</div>
                        <div class="font-semibold text-gray-800">{{ __('3️⃣ Come back each round') }}</div>
                        <div class="text-gray-500 text-xs mt-0.5">{{ __('Next round opens once the manager enters results.') }}</div>
                    </div>
                </div>
            </div>

            @foreach (\App\Models\Pool::ROUND_SEQUENCE as $round)
                @php($r = $rounds[$round])
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-800">{{ __($r['label']) }}</h3>
                        @if ($r['complete'])
                            <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ __('finished') }}</span>
                        @elseif ($r['locked'])
                            <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">{{ __('locked') }}</span>
                        @elseif ($r['open'])
                            <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded-full bg-green-50 text-green-700">{{ __('open') }}</span>
                        @else
                            <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded-full bg-gray-100 text-gray-400">{{ __('upcoming') }}</span>
                        @endif
                    </div>

                    @if ($r['open'])
                        {{-- Editable: pick winners for this round --}}
                        <form method="POST" action="{{ route('pools.picks.round', $pool) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="round" value="{{ $round }}">

                            <div class="divide-y divide-gray-100">
                                @foreach ($r['matches'] as $m)
                                    @php($sel = (int) old('winners.' . $m->id, $picks[$m->id] ?? 0))
                                    <div class="py-3"
                                         x-data="{ sel: {{ $sel }} }">
                                        <input type="hidden" name="winners[{{ $m->id }}]" :value="sel">
                                        <div class="flex items-center gap-2 text-sm">
                                            <span class="text-gray-400 w-6 shrink-0 text-right">{{ $m->position }}.</span>
                                            {{-- Team A --}}
                                            <button type="button"
                                                    @click="sel = {{ $m->team_a_id }}"
                                                    :class="sel == {{ $m->team_a_id }}
                                                        ? 'bg-indigo-600 text-white border-indigo-600'
                                                        : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                                    class="flex-1 border rounded-md px-3 py-2 text-left transition">
                                                <x-flag :code="$m->teamA?->country_code" />{{ $m->teamA?->name }}
                                            </button>
                                            <span class="text-gray-400 text-xs shrink-0">{{ __('vs') }}</span>
                                            {{-- Team B --}}
                                            <button type="button"
                                                    @click="sel = {{ $m->team_b_id }}"
                                                    :class="sel == {{ $m->team_b_id }}
                                                        ? 'bg-indigo-600 text-white border-indigo-600'
                                                        : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                                    class="flex-1 border rounded-md px-3 py-2 text-left transition">
                                                <x-flag :code="$m->teamB?->country_code" />{{ $m->teamB?->name }}
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <x-input-error :messages="$errors->get('picks')" class="mt-1" />

                            @if ($round === 'FINAL')
                                @php($f = $r['matches']->first())
                                <div class="pt-2 border-t border-gray-100">
                                    <p class="text-sm text-gray-600 mb-2">{{ __('🏆 Tie-breaker — predicted Final score') }}</p>
                                    <div class="flex items-center gap-2 text-sm">
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

                            <x-primary-button>{{ __('Save :round', ['round' => __($r['label'])]) }}</x-primary-button>
                        </form>
                    @elseif ($r['locked'] || $r['complete'])
                        {{-- Locked or finished: show this player's picks read-only --}}
                        <ul class="divide-y divide-gray-100 text-sm">
                            @foreach ($r['matches'] as $m)
                                @php($picked = $picks[$m->id] ?? null)
                                @php($decided = $m->actual_winner_team_id !== null)
                                @php($correct = $decided && $picked === $m->actual_winner_team_id)
                                <li class="flex flex-wrap items-center gap-2 py-2">
                                    <span class="text-gray-400 w-6 text-right">{{ $m->position }}.</span>
                                    <span class="text-gray-500"><x-flag :code="$m->teamA?->country_code" />{{ $m->teamA?->name }} {{ __('vs') }} <x-flag :code="$m->teamB?->country_code" />{{ $m->teamB?->name }}</span>
                                    <span class="text-gray-300">·</span>
                                    <span class="font-medium {{ $decided ? ($correct ? 'text-green-700' : 'text-red-600') : 'text-gray-800' }}">
                                        @if ($picked)<x-flag :code="$teamCodes[$picked] ?? null" />{{ $teams[$picked] ?? '—' }}@else {{ __('no pick') }} @endif
                                    </span>
                                    @if ($decided)
                                        {!! $correct ? '<span class="text-green-600">✓</span>' : '<span class="text-red-500">✗</span>' !!}
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if ($round === 'FINAL' && $finalScoreA !== null && $finalScoreB !== null)
                            @php($f = $r['matches']->first())
                            <p class="mt-3 text-sm text-gray-600">
                                {{ __('Your predicted Final score:') }}
                                <span class="font-medium"><x-flag :code="$f->teamA?->country_code" />{{ $f->teamA?->name }} {{ $finalScoreA }} – {{ $finalScoreB }} <x-flag :code="$f->teamB?->country_code" />{{ $f->teamB?->name }}</span>
                            </p>
                        @endif
                    @else
                        <p class="text-sm text-gray-400 italic">
                            @if ($round === 'R32')
                                {{ __('Opens when the manager opens the pool for picks.') }}
                            @else
                                {{ __('Opens after the :round results are in.', ['round' => __($labels[$feeders[$round] ?? ''] ?? 'previous round')]) }}
                            @endif
                        </p>
                    @endif
                </div>
            @endforeach

            <a href="{{ route('pools.show', $pool) }}" class="text-sm text-gray-600 underline">{{ __('Back to pool') }}</a>
        </div>
    </div>
</x-app-layout>
