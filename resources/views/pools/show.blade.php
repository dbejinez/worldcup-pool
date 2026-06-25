<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $pool->name }}
            </h2>
            <span class="text-xs font-semibold uppercase px-2 py-1 rounded bg-amber-100 text-amber-800">
                {{ $pool->status }}
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="px-4 py-3 bg-green-100 text-green-800 rounded-md">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('reset_password'))
                @php($rp = session('reset_password'))
                @php($subject = "Your temporary password for {$pool->name}")
                @php($body = "You've been given a temporary password for the World Cup pool \"{$pool->name}\".\n\n"
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
                    <div class="text-blue-800">
                        The player will be <strong>required to set a new password</strong> the next time they sign in. (Shown only once.)
                    </div>
                    <a href="{{ $mailto }}"
                       class="inline-flex items-center px-3 py-1.5 text-xs font-semibold uppercase rounded bg-indigo-600 text-white hover:bg-indigo-500">
                        Send via Outlook
                    </a>
                </div>
            @endif

            @unless ($pool->isApproved())
                <div class="px-4 py-3 bg-orange-50 text-orange-800 rounded-md text-sm">
                    This pool is <strong>awaiting admin approval</strong>. It can't be opened for picks until an admin approves it.
                </div>
            @endunless

            <div class="grid gap-6 md:grid-cols-3">
                {{-- Overview --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6 md:col-span-2 space-y-3">
                    <h3 class="font-semibold text-gray-800">Overview</h3>
                    <dl class="text-sm text-gray-700 grid grid-cols-2 gap-y-2">
                        <dt class="text-gray-500">Your role</dt>
                        <dd class="font-medium">{{ ucfirst($membership->role) }}</dd>

                        <dt class="text-gray-500">Type</dt>
                        <dd class="font-medium">{{ $pool->isIncremental() ? 'Incremental' : 'Full bracket' }}</dd>

                        <dt class="text-gray-500">Status</dt>
                        <dd class="font-medium">{{ ucfirst($pool->status) }}</dd>

                        <dt class="text-gray-500">Teams loaded</dt>
                        <dd class="font-medium">{{ $pool->teams->count() }} / 32</dd>

                        <dt class="text-gray-500">Pick deadline (target)</dt>
                        <dd class="font-medium">
                            {{ $pool->deadline_utc
                                ? $pool->deadline_utc->timezone($pool->timezone)->format('M j, Y g:i A') . ' (' . $pool->timezone . ')'
                                : 'Not set' }}
                        </dd>
                    </dl>
                </div>

                {{-- Scoring --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3">
                    <h3 class="font-semibold text-gray-800">Scoring (points)</h3>
                    @php($s = $pool->scoringConfig)
                    <dl class="text-sm text-gray-700 grid grid-cols-2 gap-y-1">
                        <dt class="text-gray-500">Round of 32</dt><dd class="text-right font-medium">{{ $s->pts_r32 }}</dd>
                        <dt class="text-gray-500">Round of 16</dt><dd class="text-right font-medium">{{ $s->pts_r16 }}</dd>
                        <dt class="text-gray-500">Quarterfinals</dt><dd class="text-right font-medium">{{ $s->pts_qf }}</dd>
                        <dt class="text-gray-500">Semifinals</dt><dd class="text-right font-medium">{{ $s->pts_sf }}</dd>
                        <dt class="text-gray-500">Third Place</dt><dd class="text-right font-medium">{{ $s->pts_third }}</dd>
                        <dt class="text-gray-500">Final</dt><dd class="text-right font-medium">{{ $s->pts_final }}</dd>
                    </dl>
                </div>
            </div>

            {{-- Actions --}}
            @php($hasBracket = $pool->teams->count() >= 32)
            <div class="bg-white shadow-sm sm:rounded-lg p-6 flex flex-wrap items-center gap-3">
                @if ($membership->isManager() && $hasBracket)
                    <a href="{{ route('pools.results.edit', $pool) }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-700">
                        Enter results
                    </a>
                    <a href="{{ route('pools.picks.import.show', $pool) }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">
                        Import picks
                    </a>
                @endif

                @if ($hasBracket)
                    <a href="{{ route('pools.picks.edit', $pool) }}"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-indigo-500">
                        {{ $pool->picksOpen() ? 'Make / edit my picks' : 'View my picks' }}
                    </a>
                @endif

                <a href="{{ route('pools.standings', $pool) }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-50">
                    Standings
                </a>

                @if ($membership->isManager())
                    @if ($pool->status === 'setup')
                        @if (! $pool->isApproved())
                            <span class="text-sm text-orange-700 font-medium">Awaiting admin approval</span>
                        @elseif ($pool->isReadyToOpen())
                            <form method="POST" action="{{ route('pools.open', $pool) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-green-500">
                                    Open pool for picks
                                </button>
                            </form>
                        @else
                            <span class="text-sm text-gray-500">Load 32 teams{{ $pool->isIncremental() ? '' : ' and set a deadline' }} to open the pool for picks.</span>
                        @endif
                    @elseif ($pool->status === 'open')
                        @if ($pool->isIncremental())
                            <a href="{{ route('pools.results.edit', $pool) }}"
                               class="text-sm text-indigo-600 underline">Lock rounds &amp; enter results →</a>
                        @else
                            <form method="POST" action="{{ route('pools.close', $pool) }}"
                                  onsubmit="return confirm('Close picks now? Players will no longer be able to make changes, and all picks become visible.');">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-red-500">
                                    Close picks
                                </button>
                            </form>
                        @endif
                    @elseif ($pool->status === 'locked')
                        <form method="POST" action="{{ route('pools.reopen', $pool) }}">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-green-500">
                                Reopen for picks
                            </button>
                        </form>
                    @endif
                @endif

                @if ($pool->status === 'open')
                    <span class="text-sm text-green-700 font-medium">Open for picks</span>
                @elseif ($pool->status === 'locked')
                    <span class="text-sm text-gray-600 font-medium">Picks closed</span>
                @elseif ($pool->status === 'complete')
                    <span class="text-sm text-gray-600 font-medium">Tournament complete</span>
                @endif
            </div>

            {{-- Members --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Members ({{ $pool->memberships->count() }})</h3>
                <ul class="divide-y">
                    @foreach ($pool->memberships as $m)
                        <li class="py-2 flex items-center justify-between text-sm gap-3">
                            <span class="min-w-0 truncate">{{ $m->user->name }} <span class="text-gray-400">{{ $m->user->email }}</span></span>
                            <div class="flex items-center gap-3 shrink-0">
                                @if ($membership->isManager())
                                    <form method="POST" action="{{ route('pools.members.reset-password', [$pool, $m->user_id]) }}"
                                          onsubmit="return confirm('Generate a new temporary password for {{ $m->user->name }}? Their current password will stop working.');">
                                        @csrf
                                        <button type="submit" class="text-xs text-indigo-600 underline">Reset password</button>
                                    </form>
                                @endif
                                <span class="inline-flex items-center text-[11px] font-medium px-2.5 py-0.5 rounded-full
                                    {{ $m->role === 'manager' ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($m->role) }}
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Manager setup checklist (features built in subsequent steps) --}}
            @if ($membership->isManager())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Manager setup</h3>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-center gap-2">
                            <span>①</span>
                            <a href="{{ route('pools.bracket.edit', $pool) }}" class="text-indigo-600 underline">
                                Load the 32 knockout teams and Round-of-32 matchups
                            </a>
                            @if ($pool->teams->count() >= 32)
                                <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded bg-green-100 text-green-800">done</span>
                            @endif
                        </li>
                        <li class="flex items-center gap-2">
                            <span>②</span>
                            <a href="{{ route('pools.settings', $pool) }}" class="text-indigo-600 underline">
                                Set scoring per round &amp; tie-breakers
                            </a>
                            @if ($pool->settings_saved_at)
                                <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded bg-green-100 text-green-800">done</span>
                            @endif
                        </li>
                        <li class="flex items-center gap-2">
                            <span>③</span>
                            <a href="{{ route('pools.settings', $pool) }}" class="text-indigo-600 underline">
                                Set the pick deadline
                            </a>
                            @if ($pool->deadline_utc)
                                <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded bg-green-100 text-green-800">done</span>
                            @endif
                        </li>
                        <li class="flex items-center gap-2">
                            <span>④</span>
                            <a href="{{ route('pools.invites.index', $pool) }}" class="text-indigo-600 underline">
                                Invite players
                            </a>
                            @php($acceptedInvites = $pool->invites->where('status', 'accepted')->count())
                            <span title="Accepted / invited players"
                                  class="text-xs font-semibold uppercase px-2 py-0.5 rounded bg-indigo-100 text-indigo-800">
                                {{ $acceptedInvites }}/{{ $pool->invites->count() }}
                            </span>
                        </li>
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
