<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $pool->name }} — {{ __('Bulk Import from Slack') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Result flash --}}
            @if ($result)
                <div class="bg-green-50 border border-green-200 rounded-lg p-5 space-y-3">
                    <p class="font-semibold text-green-800">
                        ✓ {{ __('Imported :count players for :round.', ['count' => $result['imported'], 'round' => $result['round']]) }}
                    </p>

                    @if (! empty($result['new_users']))
                        <div>
                            <p class="text-sm font-semibold text-green-900 mb-2">
                                {{ __('New accounts created — share these temporary passwords:') }}
                            </p>
                            <table class="w-full text-sm border border-green-200 rounded overflow-hidden">
                                <thead class="bg-green-100">
                                    <tr>
                                        <th class="text-left px-3 py-1.5 text-green-800 font-semibold">{{ __('Name') }}</th>
                                        <th class="text-left px-3 py-1.5 text-green-800 font-semibold">{{ __('Email') }}</th>
                                        <th class="text-left px-3 py-1.5 text-green-800 font-semibold">{{ __('Temp password') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($result['new_users'] as $email => $info)
                                        <tr class="border-t border-green-200 bg-white">
                                            <td class="px-3 py-1.5 text-gray-700">{{ $info['name'] }}</td>
                                            <td class="px-3 py-1.5 text-gray-700">{{ $email }}</td>
                                            <td class="px-3 py-1.5">
                                                <code class="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-800">{{ $info['password'] }}</code>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="text-xs text-green-700 mt-2">
                                {{ __('New players must change their password on first login.') }}
                                {{ __('Shown only once.') }}
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Errors --}}
            @if ($errors->any())
                <div class="px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Upload form --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div>
                    <h3 class="font-semibold text-gray-800">{{ __('Upload Slack / Forms export') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ __('Upload the .xlsx file exported from Microsoft Forms or Slack. Each row is one player; each column (after the first five metadata columns) is one match in the round.') }}
                    </p>
                </div>

                <form method="POST"
                      action="{{ route('pools.picks.bulk-import.preview', $pool) }}"
                      enctype="multipart/form-data"
                      class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Excel file (.xlsx)') }}</label>
                        <input type="file" name="file" accept=".xlsx" required
                               class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md px-3 py-2">
                    </div>

                    <div>
                        <label for="round" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Round') }}
                        </label>
                        <select name="round" id="round"
                                class="block w-full border border-gray-300 rounded-md text-sm px-3 py-2 bg-white">
                            <option value="">{{ __('Auto-detect') }}</option>
                            <option value="R32">{{ __('Round of 32') }}</option>
                            <option value="R16">{{ __('Round of 16') }}</option>
                            <option value="QF">{{ __('Quarterfinals') }}</option>
                            <option value="SF">{{ __('Semifinals') }}</option>
                            <option value="THIRD">{{ __('Third Place Match') }}</option>
                            <option value="FINAL">{{ __('Final') }}</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">
                            {{ __('For single-match rounds (Third Place, Final), select the round manually to avoid ambiguity.') }}
                        </p>
                    </div>

                    <x-primary-button>{{ __('Preview import') }}</x-primary-button>
                </form>
            </div>

            <a href="{{ route('pools.show', $pool) }}" class="text-sm text-gray-600 underline">
                {{ __('Back to pool') }}
            </a>
        </div>
    </div>
</x-app-layout>
