<x-guest-layout>
    @if (! $pool)
        <div class="text-center">
            <h2 class="text-lg font-semibold text-gray-800">Link not available</h2>
            <p class="mt-2 text-sm text-gray-600">
                This join link is invalid or the pool no longer exists.
                Ask the pool manager for a new link.
            </p>
        </div>
    @else
        <div class="space-y-4">
            <div class="text-center">
                <h2 class="text-lg font-semibold text-gray-800">You're invited!</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Join the World Cup pool <strong>{{ $pool->name }}</strong> as a player.
                </p>
            </div>

            <p class="text-sm text-center text-gray-600">
                Create a free account or log in — you'll be added to the pool automatically.
            </p>

            <div class="flex gap-3">
                <a href="{{ route('login') }}"
                   class="flex-1 text-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-sm uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                    Log in
                </a>
                <a href="{{ route('register') }}"
                   class="flex-1 text-center px-4 py-2 bg-indigo-600 text-white rounded-md font-semibold text-sm uppercase tracking-widest hover:bg-indigo-500">
                    Create account
                </a>
            </div>
        </div>
    @endif
</x-guest-layout>
