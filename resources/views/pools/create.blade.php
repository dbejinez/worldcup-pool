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
                    <form method="POST" action="{{ route('pools.store') }}" class="space-y-6">
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
                                <input type="radio" name="method" value="full" class="mt-1"
                                       @checked(old('method', 'full') === 'full')>
                                <span class="text-sm">
                                    <span class="font-medium">Full bracket</span> — players predict the
                                    <strong>entire</strong> bracket (R32 → Final) up front, before the tournament.
                                </span>
                            </label>
                            <label class="flex items-start gap-2 p-3 border rounded-md cursor-pointer">
                                <input type="radio" name="method" value="incremental" class="mt-1"
                                       @checked(old('method') === 'incremental')>
                                <span class="text-sm">
                                    <span class="font-medium">Incremental</span> — players pick
                                    <strong>one round at a time</strong> using the real teams that advance; a new
                                    round opens after the manager enters the previous round's results.
                                </span>
                            </label>
                            <x-input-error :messages="$errors->get('method')" class="mt-2" />
                        </div>

                        <p class="text-sm text-gray-500">
                            You'll be set as the pool <strong>manager</strong>. After creating it you can load the
                            32 teams, set scoring and the pick deadline, and invite players.
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
