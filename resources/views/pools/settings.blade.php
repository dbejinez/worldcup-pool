<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $pool->name }} — {{ __('Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 bg-green-100 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif

            @php
                $order = (is_array($pool->tiebreaker_order) && count($pool->tiebreaker_order) === count(\App\Models\Pool::TIEBREAKERS))
                    ? $pool->tiebreaker_order
                    : array_keys(\App\Models\Pool::TIEBREAKERS);
                $deadlineLocal = $pool->deadline_utc
                    ? $pool->deadline_utc->timezone($pool->timezone)->format('Y-m-d\TH:i')
                    : '';
                $s = $pool->scoringConfig;
            @endphp

            <form method="POST" action="{{ route('pools.settings.update', $pool) }}"
                  class="bg-white shadow-sm sm:rounded-lg p-6 space-y-8">
                @csrf
                @method('PATCH')

                {{-- Name --}}
                <div>
                    <x-input-label for="name" :value="__('Pool name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                  :value="old('name', $pool->name)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                {{-- Scoring --}}
                <div>
                    <h3 class="font-semibold text-gray-800 mb-1">{{ __('Knockout scoring (points per correct pick)') }}</h3>
                    <p class="text-sm text-gray-500 mb-3">{{ __('Later rounds are usually worth more.') }}</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        @foreach ([
                            'pts_r32' => 'Round of 32',
                            'pts_r16' => 'Round of 16',
                            'pts_qf' => 'Quarterfinals',
                            'pts_sf' => 'Semifinals',
                            'pts_third' => 'Third Place',
                            'pts_final' => 'Final',
                        ] as $field => $label)
                            <div>
                                <x-input-label :for="$field" :value="__($label)" />
                                <x-text-input :id="$field" :name="$field" type="number" min="0" max="1000"
                                              class="mt-1 block w-full"
                                              :value="old($field, $s->{$field})" required />
                                <x-input-error :messages="$errors->get($field)" class="mt-2" />
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Tie-breakers --}}
                <div>
                    <h3 class="font-semibold text-gray-800 mb-1">{{ __('Tie-breakers') }}</h3>
                    <p class="text-sm text-gray-500 mb-3">{{ __('Applied in order when players are tied on points. Each must be different.') }}</p>
                    <div class="space-y-3">
                        @for ($i = 0; $i < 4; $i++)
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500 w-28">{{ __([
                                    '1st tie-breaker',
                                    '2nd tie-breaker',
                                    '3rd tie-breaker',
                                    '4th tie-breaker',
                                ][$i]) }}</span>
                                <select name="tiebreakers[]" class="flex-1 text-sm border-gray-300 rounded-md" required>
                                    @foreach (\App\Models\Pool::TIEBREAKERS as $key => $label)
                                        <option value="{{ $key }}"
                                            @selected(old("tiebreakers.$i", $order[$i] ?? '') === $key)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endfor
                    </div>
                    <x-input-error :messages="$errors->get('tiebreakers')" class="mt-2" />
                    @for ($i = 0; $i < 4; $i++)
                        <x-input-error :messages="$errors->get('tiebreakers.' . $i)" class="mt-2" />
                    @endfor
                </div>

                {{-- Deadline --}}
                <div>
                    <h3 class="font-semibold text-gray-800 mb-1">{{ __('Pick deadline') }}</h3>
                    <p class="text-sm text-gray-500 mb-3">
                        {{ __('Shown to players as the target close time, in Central Time, Mexico') }} ({{ $pool->timezone }}).
                        {{ __("Picks don't lock automatically — you close them with the Close picks button when ready. Leave blank to set later.") }}
                    </p>
                    <x-text-input id="deadline_local" name="deadline_local" type="datetime-local"
                                  class="block w-full sm:w-auto"
                                  :value="old('deadline_local', $deadlineLocal)" />
                    <x-input-error :messages="$errors->get('deadline_local')" class="mt-2" />
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Save settings') }}</x-primary-button>
                    <a href="{{ route('pools.show', $pool) }}" class="text-sm text-gray-600 underline">{{ __('Back to pool') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
