<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $pool->name }} — Teams &amp; Bracket
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 bg-green-100 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="px-4 py-3 bg-red-100 text-red-800 rounded-md">{{ session('error') }}</div>
            @endif

            @if ($r32->isNotEmpty())
                {{-- Loaded: read-only list of the 16 R32 matchups --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Round of 32 — {{ $r32->count() }} matchups loaded</h3>
                    <ol class="grid sm:grid-cols-2 gap-2 text-sm">
                        @foreach ($r32 as $match)
                            <li class="flex items-center gap-2 py-1">
                                <span class="text-gray-400 w-6 text-right">{{ $match->position }}.</span>
                                <span class="font-medium"><x-flag :code="$match->teamA?->country_code" />{{ $match->teamA?->name }}</span>
                                <span class="text-gray-400">vs</span>
                                <span class="font-medium"><x-flag :code="$match->teamB?->country_code" />{{ $match->teamB?->name }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                @if ($pool->status === 'setup')
                    <form method="POST" action="{{ route('pools.bracket.destroy', $pool) }}"
                          onsubmit="return confirm('Reset the bracket? This deletes all teams and matches for this pool.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 underline">Reset bracket</button>
                    </form>
                @endif
            @else
                {{-- Not loaded: entry form for the 16 matchups --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6"
                     x-data="{
                        matchups: @js(old('matchups', array_fill(0, 16, ['a' => '', 'b' => '']))),
                        pasteText: '',
                        fillFromList() {
                            const lines = this.pasteText.split('\n').map(l => l.trim()).filter(l => l !== '');
                            for (let i = 0; i < 16; i++) {
                                this.matchups[i] = { a: lines[i*2] || '', b: lines[i*2+1] || '' };
                            }
                        }
                     }">
                    <h3 class="font-semibold text-gray-800 mb-2">Load the 32 teams</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Enter both teams for each of the 16 Round-of-32 matchups. The rest of the bracket
                        (R16 → Final + Third Place) is generated automatically.
                    </p>

                    @if ($errors->any())
                        <div class="mb-4 px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Quick fill helper --}}
                    <div class="mb-6 border rounded-md p-4 bg-gray-50">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Quick fill — paste 32 team names, one per line
                        </label>
                        <textarea x-model="pasteText" rows="4"
                                  class="w-full text-sm border-gray-300 rounded-md"
                                  placeholder="Mexico&#10;South Africa&#10;Canada&#10;Switzerland&#10;..."></textarea>
                        <button type="button" @click="fillFromList()"
                                class="mt-2 inline-flex items-center px-3 py-1.5 bg-gray-700 text-white text-xs font-semibold uppercase rounded hover:bg-gray-600">
                            Fill matchups from list
                        </button>
                        <p class="text-xs text-gray-500 mt-1">Lines 1 &amp; 2 become Match 1, lines 3 &amp; 4 become Match 2, and so on.</p>
                    </div>

                    <form method="POST" action="{{ route('pools.bracket.store', $pool) }}">
                        @csrf
                        <div class="space-y-2">
                            <template x-for="(pair, index) in matchups" :key="index">
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-400 w-14 text-sm" x-text="'Match ' + (index + 1)"></span>
                                    <input type="text" :name="'matchups[' + index + '][a]'" x-model="pair.a"
                                           placeholder="Team A" required
                                           class="flex-1 text-sm border-gray-300 rounded-md">
                                    <span class="text-gray-400 text-sm">vs</span>
                                    <input type="text" :name="'matchups[' + index + '][b]'" x-model="pair.b"
                                           placeholder="Team B" required
                                           class="flex-1 text-sm border-gray-300 rounded-md">
                                </div>
                            </template>
                        </div>

                        <div class="mt-6 flex items-center gap-4">
                            <x-primary-button>{{ __('Create Bracket') }}</x-primary-button>
                            <a href="{{ route('pools.show', $pool) }}" class="text-sm text-gray-600 underline">Back to pool</a>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
