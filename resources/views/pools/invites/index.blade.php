<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $pool->name }} — Invite Players
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 bg-green-100 text-green-800 rounded-md">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="px-4 py-3 bg-red-100 text-red-800 rounded-md">{{ session('error') }}</div>
            @endif

            {{-- Shareable join link --}}
            @php
                $joinUrl = route('pool.join', $pool->join_token);
                $waText = urlencode("Join my World Cup pool \"{$pool->name}\" \xe2\x80\x94 click to sign up and make your picks: {$joinUrl}");
            @endphp
            <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ copied: false }">
                <h3 class="font-semibold text-gray-800 mb-1">Share link (no email needed)</h3>
                <p class="text-sm text-gray-500 mb-3">
                    Anyone with this link can register and join the pool. Share it on WhatsApp or anywhere else.
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" readonly value="{{ $joinUrl }}"
                           class="flex-1 min-w-0 text-xs bg-gray-50 border-gray-200 rounded-md text-gray-600">
                    <button type="button"
                            @click="navigator.clipboard.writeText(@js($joinUrl)); copied = true; setTimeout(() => copied = false, 1500)"
                            class="px-3 py-1.5 text-xs font-semibold uppercase rounded bg-gray-700 text-white hover:bg-gray-600 whitespace-nowrap">
                        <span x-show="!copied">Copy link</span>
                        <span x-show="copied" x-cloak>Copied!</span>
                    </button>
                    <a href="https://wa.me/?text={{ $waText }}"
                       target="_blank" rel="noopener"
                       class="px-3 py-1.5 text-xs font-semibold uppercase rounded bg-green-600 text-white hover:bg-green-500 whitespace-nowrap">
                        WhatsApp
                    </a>
                </div>
                <form method="POST" action="{{ route('pools.join-link.regenerate', $pool) }}"
                      class="mt-3"
                      onsubmit="return confirm('This will invalidate the current link. Continue?');">
                    @csrf
                    <button type="submit" class="text-xs text-red-500 hover:underline">Regenerate link</button>
                </form>
            </div>

            {{-- Add emails --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-2">Add players</h3>
                <p class="text-sm text-gray-500 mb-3">
                    Paste player emails (one per line, or separated by commas). We'll create a unique join link for each.
                </p>
                <form method="POST" action="{{ route('pools.invites.store', $pool) }}" class="space-y-3">
                    @csrf
                    <textarea name="emails" rows="4" class="w-full text-sm border-gray-300 rounded-md"
                              placeholder="alice@example.com&#10;bob@example.com">{{ old('emails') }}</textarea>
                    <x-input-error :messages="$errors->get('emails')" class="mt-1" />
                    <x-primary-button>{{ __('Create invites') }}</x-primary-button>
                </form>
            </div>

            {{-- Existing invites --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Invites ({{ $invites->count() }})</h3>
                @forelse ($invites as $invite)
                    @php
                        $joinUrl = route('invite.show', $invite->token);
                        $subject = "You're invited to {$pool->name}";
                        $body = "You've been invited to join the World Cup pool \"{$pool->name}\".\n\n"
                              . "Click this link to register and make your picks:\n{$joinUrl}\n";
                        $mailto = 'mailto:' . $invite->email
                            . '?subject=' . rawurlencode($subject)
                            . '&body=' . rawurlencode($body);
                    @endphp
                    <div class="py-4 border-b last:border-0" x-data="{ copied: false }">
                        <div class="flex items-center justify-between">
                            <div class="font-medium text-gray-900">{{ $invite->email }}</div>
                            <span class="text-xs font-semibold uppercase px-2 py-1 rounded
                                {{ $invite->status === 'accepted' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $invite->status }}
                            </span>
                        </div>

                        @if ($invite->status === 'pending')
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <input type="text" readonly value="{{ $joinUrl }}"
                                       class="flex-1 min-w-0 text-xs bg-gray-50 border-gray-200 rounded-md text-gray-600">
                                <button type="button"
                                        @click="navigator.clipboard.writeText(@js($body)); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="px-3 py-1.5 text-xs font-semibold uppercase rounded bg-gray-700 text-white hover:bg-gray-600">
                                    <span x-show="!copied">Copy message</span>
                                    <span x-show="copied" x-cloak>Copied!</span>
                                </button>
                                <a href="{{ $mailto }}"
                                   class="px-3 py-1.5 text-xs font-semibold uppercase rounded bg-indigo-600 text-white hover:bg-indigo-500">
                                    Send via Outlook
                                </a>
                                <form method="POST" action="{{ route('pools.invites.destroy', [$pool, $invite]) }}"
                                      onsubmit="return confirm('Revoke this invite?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold uppercase rounded border border-red-300 text-red-600 hover:bg-red-50">
                                        Revoke
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No invites yet.</p>
                @endforelse
            </div>

            <a href="{{ route('pools.show', $pool) }}" class="text-sm text-gray-600 underline">Back to pool</a>
        </div>
    </div>
</x-app-layout>
