<x-guest-layout>
    @if (! $valid)
        <div class="text-center">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Invite not available') }}</h2>
            <p class="mt-2 text-sm text-gray-600">
                {{ __('This invite link is invalid, has already been used, or has expired. Please ask the pool manager for a new one.') }}
            </p>
        </div>
    @elseif ($mismatch)
        <div class="space-y-4">
            <div class="text-center">
                <h2 class="text-lg font-semibold text-gray-800">{{ __("You're invited!") }}</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Join <strong>{{ $invite->pool->name }}</strong> as a player.
                </p>
            </div>

            <div class="text-sm bg-amber-50 text-amber-800 rounded-md p-3">
                This invite was sent to <strong>{{ $invite->email }}</strong>, but you're signed in as
                <strong>{{ $currentUser->email }}</strong>. Choose how to continue.
            </div>

            <form method="POST" action="{{ route('invite.accept', $token) }}">
                @csrf
                <button type="submit"
                        class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md font-semibold text-sm uppercase tracking-widest hover:bg-indigo-500">
                    Join as {{ $currentUser->name }}
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full px-4 py-2 border border-gray-300 rounded-md font-semibold text-sm uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                    {{ __('Log out & sign in as the invited person') }}
                </button>
            </form>
            <p class="text-xs text-center text-gray-500">After logging out, open the invite link again to sign in as {{ $invite->email }}.</p>
        </div>
    @else
        <div class="space-y-4">
            <div class="text-center">
                <h2 class="text-lg font-semibold text-gray-800">{{ __("You're invited!") }}</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Join the World Cup pool <strong>{{ $invite->pool->name }}</strong> as a player.
                </p>
            </div>

            <p class="text-sm text-center text-gray-600">
                Log in or create an account to join — you'll be added to the pool automatically.
            </p>
            <div class="flex gap-3">
                <a href="{{ route('login') }}"
                   class="flex-1 text-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-sm uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                    {{ __('Log in') }}
                </a>
                <a href="{{ route('register') }}"
                   class="flex-1 text-center px-4 py-2 bg-indigo-600 text-white rounded-md font-semibold text-sm uppercase tracking-widest hover:bg-indigo-500">
                    {{ __('Create account') }}
                </a>
            </div>
        </div>
    @endif
</x-guest-layout>
