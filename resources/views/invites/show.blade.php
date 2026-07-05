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
                    {!! __('Join :pool as a player.', ['pool' => '<strong>' . e($invite->pool->name) . '</strong>']) !!}
                </p>
            </div>

            <div class="text-sm bg-amber-50 text-amber-800 rounded-md p-3">
                {!! __('This invite was sent to :invited_email, but you\'re signed in as :current_email. Choose how to continue.', [
                    'invited_email' => '<strong>' . e($invite->email) . '</strong>',
                    'current_email' => '<strong>' . e($currentUser->email) . '</strong>',
                ]) !!}
            </div>

            <form method="POST" action="{{ route('invite.accept', $token) }}">
                @csrf
                <button type="submit"
                        class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md font-semibold text-sm uppercase tracking-widest hover:bg-indigo-500">
                    {{ __('Join as') }} {{ $currentUser->name }}
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full px-4 py-2 border border-gray-300 rounded-md font-semibold text-sm uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                    {{ __('Log out & sign in as the invited person') }}
                </button>
            </form>
            <p class="text-xs text-center text-gray-500">{{ __('After logging out, open the invite link again to sign in as :email.', ['email' => $invite->email]) }}</p>
        </div>
    @else
        <div class="space-y-4">
            <div class="text-center">
                <h2 class="text-lg font-semibold text-gray-800">{{ __("You're invited!") }}</h2>
                <p class="mt-1 text-sm text-gray-600">
                    {!! __('Join the World Cup pool :pool as a player.', ['pool' => '<strong>' . e($invite->pool->name) . '</strong>']) !!}
                </p>
            </div>

            <p class="text-sm text-center text-gray-600">
                {{ __("Log in or create an account to join — you'll be added to the pool automatically.") }}
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
