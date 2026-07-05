<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $pool->name }} — {{ __('Enter Results') }}
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
                {{ __('Select the actual winner of each completed match, then save that round. Later rounds fill in automatically as you enter earlier results, and standings recompute every time you save.') }}
            </div>

            @php($roundLabels = [
                'R32' => __('Round of 32'), 'R16' => __('Round of 16'), 'QF' => __('Quarterfinals'),
                'SF' => __('Semifinals'), 'THIRD' => __('Third Place Match'), 'FINAL' => __('Final')
            ])

            @foreach ($roundOrder as $round)
                @php($roundMatches = $matches->get($round))
                @continue(! $roundMatches)

                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-800">{{ $roundLabels[$round] }}</h3>
                        @if ($pool->isIncremental())
                            @if ($pool->roundComplete($round))
                                <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ __('results in') }}</span>
                            @elseif ($pool->roundLocked($round))
                                <form method="POST" action="{{ route('pools.rounds.unlock', [$pool, $round]) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold uppercase px-3 py-1 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">{{ __('Unlock picks') }}</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('pools.rounds.lock', [$pool, $round]) }}"
                                      onsubmit="return confirm(@js(__('Lock :round picks? Players can no longer change them, and the picks become visible.', ['round' => $roundLabels[$round]])));">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold uppercase px-3 py-1 rounded bg-red-600 text-white hover:bg-red-500">{{ __('Lock picks') }}</button>
                                </form>
                            @endif
                        @endif
                    </div>

                    {{-- Results form (separate from the lock form above). --}}
                    <form method="POST" action="{{ route('pools.results.update', $pool) }}">
                        @csrf
                        @method('PUT')

                    <div class="space-y-4">
                        @foreach ($roundMatches as $m)
                            <div class="border-b last:border-0 pb-3">
                                @if ($m->team_a_id && $m->team_b_id)
                                    @php($sel = (int) old('winners.' . $m->id, $m->actual_winner_team_id ?? 0))
                                    <div class="flex flex-wrap items-center gap-4 text-sm">
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

                                    @if ($round === 'FINAL')
                                        <div class="mt-3 flex items-center gap-3 text-sm">
                                            <span class="text-gray-600">{{ __('Final score (tie-breaker):') }}</span>
                                            <span class="font-medium"><x-flag :code="$m->teamA?->country_code" />{{ $m->teamA?->name }}</span>
                                            <input type="number" name="final_score_a" min="0" max="99"
                                                   value="{{ old('final_score_a', $m->final_actual_score_a) }}"
                                                   class="w-16 text-sm border-gray-300 rounded-md">
                                            <span class="text-gray-400">–</span>
                                            <input type="number" name="final_score_b" min="0" max="99"
                                                   value="{{ old('final_score_b', $m->final_actual_score_b) }}"
                                                   class="w-16 text-sm border-gray-300 rounded-md">
                                            <span class="font-medium"><x-flag :code="$m->teamB?->country_code" />{{ $m->teamB?->name }}</span>
                                        </div>
                                        <x-input-error :messages="$errors->get('final_score')" class="mt-2" />
                                    @endif
                                @else
                                    <div class="flex items-center gap-3 text-sm text-gray-400">
                                        <span class="w-6">{{ $m->position }}.</span>
                                        <em>{{ __('Awaiting earlier results…') }}</em>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        <x-primary-button>{{ __('Save :round', ['round' => $roundLabels[$round]]) }}</x-primary-button>
                    </div>
                    </form>
                </div>
            @endforeach

            <a href="{{ route('pools.show', $pool) }}" class="inline-block text-sm text-gray-600 underline">{{ __('Back to pool') }}</a>
        </div>
    </div>
</x-app-layout>
