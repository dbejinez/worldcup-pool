<div class="bg-white shadow-sm sm:rounded-lg p-6">
    <h3 class="font-semibold text-gray-800 mb-4">My bracket</h3>

    <style>
        .wcb{display:flex;overflow-x:auto;padding-bottom:8px}
        .wcb-col{display:flex;flex-direction:column;justify-content:space-around;min-width:150px;flex:0 0 auto}
        .wcb-h{font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:#6b7280;text-align:center;margin-bottom:8px}
        .wcb-m{position:relative;margin:6px 20px 6px 0}
        .wcb-card{border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;background:#fff}
        .wcb-tm{display:flex;align-items:center;gap:6px;padding:5px 8px;font-size:12px;color:#4b5563;white-space:nowrap}
        .wcb-tm + .wcb-tm{border-top:1px solid #f3f4f6}
        .wcb-win{background:#ecfdf5;color:#065f46;font-weight:500}
        .wcb-col:not(:last-child) .wcb-m::after{content:"";position:absolute;top:50%;right:-20px;width:20px;height:2px;background:#d1d5db}
        .wcb-na{color:#9ca3af}
    </style>

    <div class="wcb">
        @foreach ($bracket as $col)
            <div class="wcb-col">
                <div class="wcb-h">{{ $col['label'] }}</div>
                @foreach ($col['matches'] as $mt)
                    <div class="wcb-m">
                        <div class="wcb-card">
                            <div class="wcb-tm {{ $mt['picked'] && $mt['a'] === $mt['picked'] ? 'wcb-win' : '' }}">
                                @if ($mt['a'])<x-flag :code="$teamCodes[$mt['a']] ?? null" />{{ $teams[$mt['a']] ?? '—' }}@else<span class="wcb-na">—</span>@endif
                            </div>
                            <div class="wcb-tm {{ $mt['picked'] && $mt['b'] === $mt['picked'] ? 'wcb-win' : '' }}">
                                @if ($mt['b'])<x-flag :code="$teamCodes[$mt['b']] ?? null" />{{ $teams[$mt['b']] ?? '—' }}@else<span class="wcb-na">—</span>@endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        @php($finalCol = end($bracket))
        @php($champId = $finalCol['matches'][0]['picked'] ?? null)
        <div class="wcb-col">
            <div class="wcb-h">Champion</div>
            <div class="wcb-m">
                <div class="wcb-card" style="border-color:#f4b400">
                    <div class="wcb-tm" style="color:#0b1f3a;font-weight:500">
                        @if ($champId)<x-flag :code="$teamCodes[$champId] ?? null" />{{ $teams[$champId] ?? '—' }}@else<span class="wcb-na">TBD</span>@endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
