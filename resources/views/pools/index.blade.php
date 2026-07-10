<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('My Pools') }}
            </h2>
            <a href="{{ route('pools.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 transition">
                {{ __('Create Pool') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))
                <div class="px-4 py-3 bg-green-100 text-green-800 rounded-md">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Block 1: pools the current user is a member of --}}
            <div>
                @if (auth()->user()->is_admin)
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">{{ __('My pools') }}</h3>
                @endif

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        @forelse ($memberships as $membership)
                            @php($pool = $membership->pool)
                            <div class="flex items-center justify-between py-4 border-b last:border-0">
                                <a href="{{ route('pools.show', $pool) }}" class="flex-1 -mx-2 px-2 py-1 rounded hover:bg-gray-50">
                                    <div class="font-medium text-gray-900">{{ $pool->name }}</div>
                                    <div class="text-sm text-gray-500">
                                        {{ __('Created by') }} {{ $pool->creator->name }}
                                    </div>
                                </a>
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center text-[11px] font-medium px-2.5 py-0.5 rounded-full
                                        {{ $membership->role === 'manager' ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $membership->role === 'manager' ? __('Manager') : __('Player') }}
                                    </span>
                                    <span class="inline-flex items-center text-[11px] font-medium px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700">
                                        {{ $pool->isIncremental() ? __('Incremental') : __('Full') }}
                                    </span>
                                    @unless ($pool->isApproved())
                                        <span class="inline-flex items-center text-[11px] font-medium px-2.5 py-0.5 rounded-full bg-orange-50 text-orange-700">
                                            {{ __('Pending approval') }}
                                        </span>
                                    @endunless
                                    <span class="inline-flex items-center text-[11px] font-medium px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700">
                                        {{ __($pool->status) }}
                                    </span>

                                    @if ($membership->role === 'manager')
                                        <form method="POST" action="{{ route('pools.destroy', $pool) }}"
                                              onsubmit="return confirm(@js(__('Delete this pool? This permanently removes the pool, its bracket, all picks and standings. This cannot be undone.')));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="{{ __('Delete') }}"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border border-red-300 text-red-600 hover:bg-red-50">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500">
                                {{ __("You're not in any pools yet.") }}
                                <a href="{{ route('pools.create') }}" class="text-indigo-600 underline">{{ __('Create one') }}</a>
                                {{ __('to get started.') }}
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Block 2: admin-only — all other pools in the system --}}
            @if (auth()->user()->is_admin && $otherPools->isNotEmpty())
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">{{ __('All other pools') }}</h3>

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900">
                            @foreach ($otherPools as $pool)
                                <div class="flex items-center justify-between py-4 border-b last:border-0">
                                    <a href="{{ route('pools.show', $pool) }}" class="flex-1 -mx-2 px-2 py-1 rounded hover:bg-gray-50">
                                        <div class="font-medium text-gray-900">{{ $pool->name }}</div>
                                        <div class="text-sm text-gray-500">
                                            {{ __('Created by') }} {{ $pool->creator->name }}
                                            · {{ $pool->memberships->count() }} {{ __('members') }}
                                        </div>
                                    </a>
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center text-[11px] font-medium px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700">
                                            {{ $pool->isIncremental() ? __('Incremental') : __('Full') }}
                                        </span>
                                        @unless ($pool->isApproved())
                                            <span class="inline-flex items-center text-[11px] font-medium px-2.5 py-0.5 rounded-full bg-orange-50 text-orange-700">
                                                {{ __('Pending approval') }}
                                            </span>
                                        @endunless
                                        <span class="inline-flex items-center text-[11px] font-medium px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700">
                                            {{ __($pool->status) }}
                                        </span>
                                        <form method="POST" action="{{ route('pools.destroy', $pool) }}"
                                              onsubmit="return confirm(@js(__('Delete this pool? This permanently removes the pool, its bracket, all picks and standings. This cannot be undone.')));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="{{ __('Delete') }}"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border border-red-300 text-red-600 hover:bg-red-50">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
