<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Admin — Users') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('reset_password'))
                @php($rp = session('reset_password'))
                @php($subject = 'Your World Cup pool temporary password')
                @php($body = "A temporary password has been set for your World Cup pool account.\n\n"
                    . "Sign in here: " . route('login') . "\n"
                    . "Email: {$rp['email']}\n"
                    . "Temporary password: {$rp['password']}\n\n"
                    . "You'll be asked to set your own password right after you sign in.\n")
                @php($mailto = 'mailto:' . $rp['email'] . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($body))
                <div class="px-4 py-3 bg-blue-50 text-blue-900 rounded-md text-sm space-y-2">
                    <div>
                        Temporary password for <strong>{{ $rp['name'] }}</strong> ({{ $rp['email'] }}):
                        <code class="mx-1 px-2 py-0.5 bg-white border border-blue-200 rounded font-mono">{{ $rp['password'] }}</code>
                    </div>
                    <div class="text-blue-800">{{ __("They'll be required to set a new password on next sign-in.") }} ({{ __('Shown only once.') }})</div>
                    <a href="{{ $mailto }}"
                       class="inline-flex items-center px-3 py-1.5 text-xs font-semibold uppercase rounded bg-indigo-600 text-white hover:bg-indigo-500">
                        {{ __('Send via Outlook') }}
                    </a>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('Search name or email') }}"
                           class="flex-1 text-sm border-gray-300 rounded-md">
                    <x-primary-button>{{ __('Search') }}</x-primary-button>
                </form>

                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="text-left px-3 py-2">{{ __('Name') }}</th>
                            <th class="text-left px-3 py-2">{{ __('Email') }}</th>
                            <th class="text-right px-3 py-2">Pools</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($users as $u)
                            <tr>
                                <td class="px-3 py-2">
                                    {{ $u->name }}
                                    @if ($u->is_admin)
                                        <span class="ml-1 text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-purple-100 text-purple-800">admin</span>
                                    @endif
                                    @if ($u->must_change_password)
                                        <span class="ml-1 text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">temp pwd</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ $u->email }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ $u->memberships_count }}</td>
                                <td class="px-3 py-2 text-right">
                                    <form method="POST" action="{{ route('admin.users.reset-password', $u) }}"
                                          onsubmit="return confirm('Issue a temporary password for {{ $u->email }}? Their current password will stop working.');">
                                        @csrf
                                        <button type="submit" class="text-xs text-indigo-600 underline">{{ __('Reset password') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-4 text-gray-500">{{ __('No users found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
