<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $pool->name }} — {{ __('Confirm Bulk Import') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Round selector (re-submit without re-uploading) --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-5">
                <form method="POST"
                      action="{{ route('pools.picks.bulk-import.preview', $pool) }}"
                      class="flex flex-wrap items-end gap-4">
                    @csrf
                    <div>
                        <label for="round" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Round') }}
                        </label>
                        <select name="round" id="round"
                                class="border border-gray-300 rounded-md text-sm px-3 py-2 bg-white">
                            <option value="">{{ __('Auto-detect') }}</option>
                            @foreach ($roundLabels as $key => $label)
                                <option value="{{ $key }}" {{ $selectedRound === $key ? 'selected' : '' }}>
                                    {{ __($label) }}
                                </option>
                            @endforeach
                        </select>
                        @if ($detectedRound)
                            <p class="mt-1 text-xs text-gray-400">
                                {{ __('Auto-detected: :round', ['round' => __($roundLabels[$detectedRound] ?? $detectedRound)]) }}
                            </p>
                        @endif
                    </div>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">
                        {{ __('Re-detect') }}
                    </button>
                </form>
            </div>

            {{-- Matches detected --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-5">
                <h3 class="font-semibold text-gray-800 mb-3">
                    {{ __(':count matches detected for :round', [
                        'count' => count($payload['match_ids']),
                        'round' => __($roundLabels[$selectedRound] ?? $selectedRound),
                    ]) }}
                </h3>
            </div>

            {{-- Players table --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">
                        {{ __(':count players found', ['count' => count($payload['players'])]) }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="text-left px-4 py-3">{{ __('Name') }}</th>
                                <th class="text-left px-4 py-3">{{ __('Email') }}</th>
                                <th class="text-left px-4 py-3">{{ __('Status') }}</th>
                                <th class="text-left px-4 py-3">{{ __('Picks') }}</th>
                                <th class="text-left px-4 py-3">{{ __('Warnings') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($payload['players'] as $player)
                                <tr class="{{ ! empty($player['warnings']) ? 'bg-amber-50' : '' }}">
                                    <td class="px-4 py-3 text-gray-900">{{ $player['name'] }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $player['email'] }}</td>
                                    <td class="px-4 py-3">
                                        @if ($player['exists'])
                                            <span class="inline-flex items-center text-[11px] font-semibold uppercase px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                                                {{ __('Existing') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center text-[11px] font-semibold uppercase px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                                                {{ __('New account') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @php($filled = $player['total_matches'] - $player['blank_count'])
                                        <span class="{{ $player['blank_count'] > 0 ? 'text-amber-700' : 'text-gray-700' }}">
                                            {{ $filled }} / {{ $player['total_matches'] }}
                                            @if ($player['blank_count'] > 0)
                                                <span class="text-xs text-amber-600">({{ $player['blank_count'] }} {{ __('blank') }})</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-amber-700">
                                        @if (! empty($player['warnings']))
                                            {{ __('Unknown team(s):') }} {{ implode(', ', $player['warnings']) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- New users notice --}}
            @php($newCount = count(array_filter($payload['players'], fn($p) => ! $p['exists'])))
            @if ($newCount > 0)
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg px-5 py-4 text-sm text-indigo-800">
                    {{ __(':count new account(s) will be created. Each new player receives a temporary password (first name + 123) and will be asked to change it on first login.', ['count' => $newCount]) }}
                </div>
            @endif

            {{-- Action buttons --}}
            <div class="flex items-center gap-4">
                <form method="POST" action="{{ route('pools.picks.bulk-import', $pool) }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white rounded-md font-semibold text-sm hover:bg-green-500 transition">
                        {{ __('Confirm Import') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('pools.picks.bulk-import.cancel', $pool) }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center px-5 py-2.5 border border-gray-300 text-gray-700 rounded-md font-semibold text-sm hover:bg-gray-50 transition">
                        {{ __('Cancel') }}
                    </button>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
