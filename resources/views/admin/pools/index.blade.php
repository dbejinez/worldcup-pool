<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin — Pool Approvals</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 bg-green-100 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Pending pools ({{ $pools->count() }})</h3>

                @forelse ($pools as $pool)
                    <div class="flex items-center justify-between py-3 border-b last:border-0">
                        <div>
                            <div class="font-medium text-gray-900">{{ $pool->name }}</div>
                            <div class="text-sm text-gray-500">
                                Requested by {{ $pool->creator->name }} ({{ $pool->creator->email }})
                                · {{ $pool->created_at->diffForHumans() }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('admin.pools.approve', $pool) }}">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 text-xs font-semibold uppercase rounded bg-green-600 text-white hover:bg-green-500">
                                    Approve
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.pools.reject', $pool) }}"
                                  onsubmit="return confirm('Reject and delete this pool request?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="px-3 py-1.5 text-xs font-semibold uppercase rounded border border-red-300 text-red-600 hover:bg-red-50">
                                    Reject
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No pools awaiting approval.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
