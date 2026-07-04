<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $pool->name }} — {{ __('Import Picks') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 bg-green-100 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="px-4 py-3 bg-red-100 text-red-800 rounded-md">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">{{ $errors->first() }}</div>
            @endif
            @if (session('import_errors'))
                <div class="px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">
                    <p class="font-semibold mb-1">{{ __("The file couldn't be imported:") }}</p>
                    <ul class="list-disc list-inside">
                        @foreach (session('import_errors') as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3">
                <h3 class="font-semibold text-gray-800">{{ __('1. Download the template') }}</h3>
                <p class="text-sm text-gray-600">
                    {{ __('One file per player. It lists the Round-of-32 matchups and a reference tab with the exact team names.') }}
                </p>
                <a href="{{ route('pools.picks.template', $pool) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-700">
                    {{ __('Download Excel template') }}
                </a>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3">
                <h3 class="font-semibold text-gray-800">{{ __('2. Upload a completed file') }}</h3>
                <p class="text-sm text-gray-600">
                    {{ __('Fill in the player\'s email, a winner for every match, and the Final score (Champion > Runner-up).') }}
                    {{ __('Accepts .xlsx or .csv. The pool must be open for picks.') }}
                </p>
                <form method="POST" action="{{ route('pools.picks.import', $pool) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.csv" required
                           class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md">
                    <x-primary-button>{{ __('Import picks') }}</x-primary-button>
                </form>
            </div>

            <a href="{{ route('pools.show', $pool) }}" class="text-sm text-gray-600 underline">{{ __('Back to pool') }}</a>
        </div>
    </div>
</x-app-layout>
