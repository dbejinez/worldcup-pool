<x-app-layout>
    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- ── Hero banner ──────────────────────────────────────────────────── --}}
            <div class="relative overflow-hidden rounded-2xl px-8 py-10 text-white shadow-lg"
                 style="background: linear-gradient(135deg, #0B1F3A 0%, #1B3A66 100%);">

                {{-- Decorative background emojis --}}
                <span class="pointer-events-none select-none absolute -right-4 -top-4 text-[9rem] leading-none opacity-10">🏆</span>
                <span class="pointer-events-none select-none absolute right-28 top-6 text-[5rem] leading-none opacity-10">⚽</span>
                <span class="pointer-events-none select-none absolute right-10 bottom-2 text-[4rem] leading-none opacity-10">🌍</span>

                <div class="relative">
                    <p class="font-display font-semibold text-sm uppercase tracking-widest mb-1 text-yellow-400">
                        {{ __('2026 FIFA World Cup Pool') }}
                    </p>
                    <h1 class="font-display font-bold text-3xl sm:text-4xl leading-tight mb-2">
                        {{ __('Welcome back, :name!', ['name' => auth()->user()->name]) }}
                    </h1>
                    <p class="text-blue-200 text-sm max-w-lg">
                        {{ __('Pick the match winners, score points for every correct prediction, and climb your group leaderboard.') }}
                    </p>

                    @if ($memberships->isEmpty())
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="{{ route('pools.create') }}"
                               class="inline-flex items-center px-5 py-2.5 rounded-lg bg-yellow-400 text-brand-navy font-semibold text-sm hover:bg-yellow-300 transition">
                                ⚽ {{ __('Create a Pool') }}
                            </a>
                            <a href="{{ route('pools.index') }}"
                               class="inline-flex items-center px-5 py-2.5 rounded-lg border border-white/30 text-white font-semibold text-sm hover:bg-white/10 transition">
                                {{ __('View my pools') }}
                            </a>
                        </div>
                    @else
                        <div class="mt-5">
                            <a href="{{ route('pools.index') }}"
                               class="inline-flex items-center px-5 py-2.5 rounded-lg bg-yellow-400 text-brand-navy font-semibold text-sm hover:bg-yellow-300 transition">
                                {{ __('View all my pools') }} →
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ── My pools cards ───────────────────────────────────────────────── --}}
            @if ($memberships->isNotEmpty())
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">
                        {{ __('My Pools') }}
                    </h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($memberships as $membership)
                            @php
                                $pool      = $membership->pool;
                                $isManager = $membership->isManager();
                                $hasBracket = $pool->teams->count() >= $pool->startRoundTeamCount();

                                // Rank: position among all members sorted by score desc.
                                $sorted  = $pool->memberships->sortByDesc('score')->values();
                                $rankPos = $sorted->search(fn ($m) => $m->user_id === $membership->user_id);
                                $rank    = $rankPos !== false ? $rankPos + 1 : null;
                                $total   = $pool->memberships->count();
                            @endphp

                            <div class="bg-white shadow-sm rounded-xl p-5 flex flex-col gap-4 border border-gray-100">

                                {{-- Pool name + status badge --}}
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <a href="{{ route('pools.show', $pool) }}"
                                           class="font-semibold text-gray-900 hover:text-indigo-600 transition leading-snug">
                                            {{ $pool->name }}
                                        </a>
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            {{ $total }} {{ __('players') }}
                                            @if ($isManager)
                                                · <span class="text-indigo-600 font-medium">{{ __('Manager') }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <span class="shrink-0 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full
                                        @if ($pool->status === 'open')     bg-green-100 text-green-700
                                        @elseif ($pool->status === 'locked')   bg-amber-100 text-amber-700
                                        @elseif ($pool->status === 'complete') bg-gray-100 text-gray-600
                                        @else bg-orange-50 text-orange-600 @endif">
                                        {{ __($pool->status) }}
                                    </span>
                                </div>

                                {{-- Stats strip --}}
                                <div class="grid grid-cols-3 divide-x divide-gray-100 text-center rounded-lg bg-gray-50 py-2">
                                    <div class="px-2">
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">{{ __('Rank') }}</p>
                                        <p class="font-display font-bold text-xl text-gray-900 leading-tight">
                                            @if ($rank !== null) #{{ $rank }} @else — @endif
                                        </p>
                                    </div>
                                    <div class="px-2">
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">{{ __('Score') }}</p>
                                        <p class="font-display font-bold text-xl text-gray-900 leading-tight">
                                            {{ $membership->score }}
                                        </p>
                                    </div>
                                    <div class="px-2">
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">{{ __('Correct') }}</p>
                                        <p class="font-display font-bold text-xl text-gray-900 leading-tight">
                                            {{ $membership->correct_picks }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Action buttons --}}
                                <div class="flex flex-wrap gap-2">
                                    @if ($hasBracket)
                                        <a href="{{ route('pools.picks.edit', $pool) }}"
                                           class="flex-1 text-center text-xs font-semibold uppercase tracking-wide px-3 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 transition">
                                            {{ $pool->picksOpen() ? __('My Picks') : __('View my picks') }}
                                        </a>
                                    @endif
                                    <a href="{{ route('pools.standings', $pool) }}"
                                       class="flex-1 text-center text-xs font-semibold uppercase tracking-wide px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">
                                        {{ __('Standings') }}
                                    </a>
                                    @if ($isManager && $hasBracket)
                                        <a href="{{ route('pools.results.edit', $pool) }}"
                                           class="flex-1 text-center text-xs font-semibold uppercase tracking-wide px-3 py-2 rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition">
                                            {{ __('Enter results') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ── How It Works ─────────────────────────────────────────────────── --}}
            <div class="bg-white shadow-sm sm:rounded-xl p-6">
                <h2 class="font-display font-semibold text-gray-800 mb-5">{{ __('How It Works') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                    <div class="sm:pr-6 pb-5 sm:pb-0">
                        <div class="text-3xl mb-2">1️⃣</div>
                        <p class="font-semibold text-gray-800 text-sm">{{ __('Join or create a pool') }}</p>
                        <p class="text-gray-500 text-xs mt-1 leading-relaxed">
                            {{ __('Share an invite link with your group — everyone joins the same pool.') }}
                        </p>
                    </div>
                    <div class="sm:px-6 py-5 sm:py-0">
                        <div class="text-3xl mb-2">2️⃣</div>
                        <p class="font-semibold text-gray-800 text-sm">{{ __('Pick the match winners') }}</p>
                        <p class="text-gray-500 text-xs mt-1 leading-relaxed">
                            {{ __('Predict who advances through every knockout round, all the way to the Final.') }}
                        </p>
                    </div>
                    <div class="sm:pl-6 pt-5 sm:pt-0">
                        <div class="text-3xl mb-2">3️⃣</div>
                        <p class="font-semibold text-gray-800 text-sm">{{ __('Score points & climb the table') }}</p>
                        <p class="text-gray-500 text-xs mt-1 leading-relaxed">
                            {{ __('Every correct pick earns points. Later rounds are worth more — keep an eye on the standings!') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- ── Scoring reference ────────────────────────────────────────────── --}}
            @php($sc = $memberships->first()?->pool?->scoringConfig)
            <div class="bg-white shadow-sm sm:rounded-xl p-6">
                <h2 class="font-display font-semibold text-gray-800 mb-4">
                    {{ __('Scoring (points per correct pick)') }}
                </h2>
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
                    @foreach ([
                        ['label' => __('Round of 32'),   'pts' => $sc?->pts_r32   ?? 1,  'bg' => 'bg-gray-50'],
                        ['label' => __('Round of 16'),   'pts' => $sc?->pts_r16   ?? 2,  'bg' => 'bg-sky-50'],
                        ['label' => __('Quarterfinals'), 'pts' => $sc?->pts_qf    ?? 4,  'bg' => 'bg-indigo-50'],
                        ['label' => __('Semifinals'),    'pts' => $sc?->pts_sf    ?? 8,  'bg' => 'bg-purple-50'],
                        ['label' => __('Third Place'),   'pts' => $sc?->pts_third ?? 4,  'bg' => 'bg-amber-50'],
                        ['label' => __('Final'),         'pts' => $sc?->pts_final ?? 16, 'bg' => 'bg-yellow-50'],
                    ] as $row)
                        <div class="flex flex-col items-center justify-center rounded-xl {{ $row['bg'] }} py-3 px-2 text-center">
                            <span class="font-display font-bold text-2xl text-gray-900 leading-none">{{ $row['pts'] }}</span>
                            <span class="text-[10px] text-gray-500 mt-1 leading-tight">{{ $row['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                @if ($memberships->count() > 1)
                    <p class="mt-3 text-xs text-gray-400">
                        {{ __("Points shown are for your first pool. Scoring may vary by pool — check each pool's settings.") }}
                    </p>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
