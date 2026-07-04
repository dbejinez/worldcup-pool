<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Pool') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('pools.store') }}" class="space-y-6"
                          x-data="{ method: @js(old('method', 'full')), startRound: @js(old('start_round', 'R32')) }">
                        @csrf

                        <div>
                            <x-input-label for="name" :value="__('Pool name')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                          :value="old('name')" required autofocus
                                          placeholder="e.g. Office World Cup 2026" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label :value="__('Pool type')" />
                            <p class="text-xs text-gray-500 mb-2">This can't be changed after the pool is created.</p>
                            <label class="flex items-start gap-2 p-3 border rounded-md cursor-pointer mb-2">
                                <input type="radio" name="method" value="full" x-model="method" class="mt-1">
                                <span class="text-sm">
                                    <span class="font-medium">Full bracket</span> — players predict the
                                    bracket up front, from the starting round through the Final.
                                </span>
                            </label>
                            <label class="flex items-start gap-2 p-3 border rounded-md cursor-pointer">
                                <input type="radio" name="method" value="incremental" x-model="method" class="mt-1">
                                <span class="text-sm">
                                    <span class="font-medium">Incremental</span> — players pick
                                    <strong>one round at a time</strong> using the real teams that advance; a new
                                    round opens after the manager enters the previous round's results.
                                </span>
                            </label>
                            <x-input-error :messages="$errors->get('method')" class="mt-2" />
                        </div>

                        <div x-show="method === 'full'" x-cloak>
                            <x-input-label :value="__('Starting round')" />
                            <p class="text-xs text-gray-500 mb-2">
                                Which round do players begin predicting from? The manager loads only the teams for that round.
                                Defaults to Round of 32 (full tournament).
                            </p>
                            <select name="start_round" x-model="startRound"
                                    class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                                <option value="R32">Round of 32 — 32 teams, 16 matchups</option>
                                <option value="R16">Round of 16 — 16 teams, 8 matchups</option>
                                <option value="QF">Quarterfinals — 8 teams, 4 matchups</option>
                                <option value="SF">Semifinals — 4 teams, 2 matchups</option>
                                <option value="FINAL">Final only — 2 teams, 1 matchup</option>
                            </select>
                            <x-input-error :messages="$errors->get('start_round')" class="mt-2" />
                        </div>

                        <p class="text-sm text-gray-500">
                            You'll be set as the pool <strong>manager</strong>. After creating it you can load the
                            teams, set scoring and the pick deadline, and invite players.
                        </p>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Create Pool') }}</x-primary-button>
                            <a href="{{ route('pools.index') }}" class="text-sm text-gray-600 underline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
