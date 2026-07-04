<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $pool->name }} — {{ __('Standings') }}</h2>
            <a href="{{ route('pools.show', $pool) }}" class="text-sm text-gray-600 underline">{{ __('Back to pool') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if ($champion)
                <div class="rounded-lg p-4 bg-brand-gold text-brand-navy flex items-center gap-3">
                    <span class="font-display font-semibold text-base uppercase tracking-wide">{{ __('Champion') }}</span>
                    <span class="text-lg font-display font-semibold flex items-center">
                        <x-flag :code="$champion->country_code" />{{ $champion->name }}
                    </span>
                    @if ($finalScoreA !== null && $finalScoreB !== null)
                        <span class="ml-auto text-sm">{{ __('Final') }} {{ $finalScoreA }}–{{ $finalScoreB }}</span>
                    @endif
                </div>
            @endif

            @unless ($revealed)
                <div class="px-4 py-3 bg-amber-100 text-amber-800 rounded-md text-sm">
                    {{ __("Other players' picks stay hidden until they're revealed.") }}
                </div>
            @endunless

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="text-left px-4 py-3 w-12">#</th>
                            <th class="text-left px-4 py-3">{{ __('Player') }}</th>
                            <th class="text-left px-4 py-3">{{ __('Champion pick') }}</th>
                            <th class="text-right px-4 py-3">{{ __('Correct') }}</th>
                            <th class="text-right px-4 py-3">{{ __('Score:') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($standings as $row)
                            @php($m = $row['membership'])
                            @php($rank = $row['rank'])
                            @php($isSelf = $m->user_id === $viewerId)
                            @php($canViewPicks = $revealed || $viewerIsManager || $isSelf)
                            @php($canSeeChamp = $championsVisible || $viewerIsManager || $isSelf)
                            @php($champPickId = $finalPicks[$m->user_id] ?? null)
                            <tr class="{{ $isSelf ? 'bg-indigo-50' : ($loop->even ? 'bg-gray-50/60' : '') }}">
                                <td class="px-4 py-3">
                                    @if ($rank <= 3)
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold
                                            {{ $rank === 1 ? 'bg-brand-gold text-brand-navy' : ($rank === 2 ? 'bg-gray-300 text-gray-800' : 'bg-amber-700 text-white') }}">
                                            {{ $rank }}
                                        </span>
                                    @else
                                        <span class="text-gray-500">{{ $rank }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ $m->user->name }}
                                    @if ($m->isManager())
                                        <span class="ml-1 text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-800">{{ __('mgr') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($canSeeChamp && $champPickId)
                                        <span class="inline-flex items-center text-gray-700"><x-flag :code="$teamCodes[$champPickId] ?? null" />{{ $teamNames[$champPickId] ?? '—' }}</span>
                                    @elseif (! $canSeeChamp)
                                        <span class="text-gray-300">{{ __('hidden') }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ $m->correct_picks }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ $m->score }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($canViewPicks)
                                        <a href="{{ route('pools.picks.show', [$pool, $m->user_id]) }}"
                                           class="text-indigo-600 underline text-xs">{{ __('View picks') }}</a>
                                    @else
                                        <span class="text-gray-300 text-xs">{{ __('hidden') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
