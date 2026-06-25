<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $pool->name }} — My Picks
        </h2>
    </x-slot>

    <style>[x-cloak]{display:none!important}</style>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6"
             x-data="pickSheet({
                matches: @js($matchesData),
                teams: @js($teams),
                teamCodes: @js($teamCodes),
                picks: @js($existingPicks),
                finalScoreA: @js($finalScoreA),
                finalScoreB: @js($finalScoreB),
                canEdit: @js($canEdit)
             })">

            @if ($errors->any())
                <div class="px-4 py-3 bg-red-100 text-red-800 rounded-md text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            @unless ($canEdit)
                <div class="px-4 py-3 bg-amber-100 text-amber-800 rounded-md text-sm">
                    {{ $closedReason }} You can view your picks but not change them.
                </div>
            @endunless

            <form method="POST" action="{{ route('pools.picks.update', $pool) }}">
                @csrf
                @method('PUT')

                {{-- Hidden submission fields, kept in sync by Alpine --}}
                <template x-for="m in matches" :key="'h' + m.id">
                    <input type="hidden" :name="`picks[${m.id}]`" :value="picks[m.id] ?? ''">
                </template>

                <div class="space-y-6">
                    <template x-for="round in roundOrder" :key="round">
                        <div class="bg-white shadow-sm sm:rounded-lg p-6">
                            <h3 class="font-semibold text-gray-800 mb-4" x-text="roundLabel(round)"></h3>

                            <div class="space-y-3">
                                <template x-for="m in matchesInRound(round)" :key="m.id">
                                    <div>
                                        <template x-if="participants(m.id)[0] && participants(m.id)[1]">
                                            <div class="flex items-center gap-2">
                                                <template x-for="slot in [0, 1]" :key="slot">
                                                    <button type="button"
                                                            @click="choose(m.id, participants(m.id)[slot])"
                                                            :disabled="!canEdit"
                                                            :class="picks[m.id] === participants(m.id)[slot]
                                                                ? 'bg-indigo-600 text-white border-indigo-600'
                                                                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                                            class="flex-1 text-sm border rounded-md px-3 py-2 text-left transition disabled:opacity-60 disabled:cursor-not-allowed">
                                                        <span class="fi" x-show="flagCode(participants(m.id)[slot])"
                                                              :class="flagCode(participants(m.id)[slot]) ? 'fi-' + flagCode(participants(m.id)[slot]) : ''"
                                                              style="display:inline-block;width:1.3em;height:0.95em;border-radius:2px;background-size:cover;background-position:center;vertical-align:-1px;margin-right:0.45em;"></span>
                                                        <span x-text="teamName(participants(m.id)[slot])"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="!(participants(m.id)[0] && participants(m.id)[1])">
                                            <p class="text-sm text-gray-400 italic">Make your earlier-round picks first.</p>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Final score (tie-breaker) --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800 mb-1">Tie-breaker — predicted Final score</h3>
                        <p class="text-sm text-gray-600 mb-3">Predict how many goals each finalist scores.</p>

                        {{-- always submit the two score fields --}}
                        <input type="hidden" name="final_score_a" :value="finalScoreA ?? ''">
                        <input type="hidden" name="final_score_b" :value="finalScoreB ?? ''">

                        <template x-if="finalReady()">
                            <div class="flex items-center gap-3 text-sm">
                                <span class="font-medium">
                                    <span class="fi" x-show="flagCode(finalParticipants()[0])"
                                          :class="flagCode(finalParticipants()[0]) ? 'fi-' + flagCode(finalParticipants()[0]) : ''"
                                          style="display:inline-block;width:1.3em;height:0.95em;border-radius:2px;background-size:cover;background-position:center;vertical-align:-1px;margin-right:0.4em;"></span>
                                    <span x-text="teamName(finalParticipants()[0])"></span>
                                </span>
                                <input type="number" min="0" max="99" x-model.number="finalScoreA" :disabled="!canEdit"
                                       class="w-16 text-sm border-gray-300 rounded-md disabled:bg-gray-100">
                                <span class="text-gray-400">–</span>
                                <input type="number" min="0" max="99" x-model.number="finalScoreB" :disabled="!canEdit"
                                       class="w-16 text-sm border-gray-300 rounded-md disabled:bg-gray-100">
                                <span class="font-medium">
                                    <span class="fi" x-show="flagCode(finalParticipants()[1])"
                                          :class="flagCode(finalParticipants()[1]) ? 'fi-' + flagCode(finalParticipants()[1]) : ''"
                                          style="display:inline-block;width:1.3em;height:0.95em;border-radius:2px;background-size:cover;background-position:center;vertical-align:-1px;margin-right:0.4em;"></span>
                                    <span x-text="teamName(finalParticipants()[1])"></span>
                                </span>
                            </div>
                        </template>
                        <p x-show="finalReady() && finalScoreFilled() && !finalScoreValid()" x-cloak
                           class="mt-2 text-sm text-red-600">
                            Your champion must score more than the other finalist — no ties.
                        </p>
                        <template x-if="!finalReady()">
                            <p class="text-sm text-gray-400 italic">Pick both finalists first, then enter the score.</p>
                        </template>
                    </div>

                    {{-- Submit --}}
                    <div class="flex items-center gap-4">
                        <button type="submit" :disabled="!canEdit || !isComplete()"
                                class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            Save my picks
                        </button>
                        <span class="text-sm text-gray-500">
                            <span x-text="completedCount()"></span> / <span x-text="matches.length"></span> matches picked
                        </span>
                        <a href="{{ route('pools.show', $pool) }}" class="text-sm text-gray-600 underline">Back to pool</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function pickSheet(config) {
            return {
                matches: config.matches,
                teams: config.teams,
                teamCodes: config.teamCodes || {},
                picks: config.picks || {},
                finalScoreA: config.finalScoreA ?? '',
                finalScoreB: config.finalScoreB ?? '',
                canEdit: config.canEdit,
                byId: {},
                roundOrder: ['R32', 'R16', 'QF', 'SF', 'THIRD', 'FINAL'],

                init() {
                    this.matches.forEach(m => this.byId[m.id] = m);
                    this.normalize();
                },

                roundLabel(r) {
                    return {
                        R32: 'Round of 32', R16: 'Round of 16', QF: 'Quarterfinals',
                        SF: 'Semifinals', THIRD: 'Third Place Match', FINAL: 'Final',
                    }[r] || r;
                },

                matchesInRound(r) {
                    return this.matches.filter(m => m.round === r).sort((a, b) => a.position - b.position);
                },

                slotTeam(id, slot) {
                    const m = this.byId[id];
                    if (!m) return null;
                    if (m.round === 'R32') return slot === 'A' ? m.team_a_id : m.team_b_id;

                    const src = slot === 'A' ? m.srcA : m.srcB;
                    if (!src) return null;
                    if (src.type === 'winner') return this.picks[src.match] ?? null;

                    // loser of the child match
                    const winner = this.picks[src.match] ?? null;
                    const a = this.slotTeam(src.match, 'A');
                    const b = this.slotTeam(src.match, 'B');
                    if (winner == null || a == null || b == null) return null;
                    return winner === a ? b : a;
                },

                participants(id) {
                    return [this.slotTeam(id, 'A'), this.slotTeam(id, 'B')];
                },

                teamName(id) {
                    return this.teams[id] || '—';
                },

                flagCode(id) {
                    return this.teamCodes[id] || null;
                },

                finalMatch() {
                    return this.matches.find(m => m.round === 'FINAL');
                },

                finalParticipants() {
                    const f = this.finalMatch();
                    return f ? this.participants(f.id) : [null, null];
                },

                finalReady() {
                    const [a, b] = this.finalParticipants();
                    return a != null && b != null;
                },

                finalScoreFilled() {
                    return this.finalScoreA !== '' && this.finalScoreA != null
                        && this.finalScoreB !== '' && this.finalScoreB != null;
                },

                // The champion must score strictly more than the other finalist (no ties).
                finalScoreValid() {
                    if (!this.finalReady() || !this.finalScoreFilled()) return false;
                    const champ = this.picks[this.finalMatch().id];
                    const [a, b] = this.finalParticipants();
                    if (champ === a) return this.finalScoreA > this.finalScoreB;
                    if (champ === b) return this.finalScoreB > this.finalScoreA;
                    return false;
                },

                choose(id, teamId) {
                    if (!this.canEdit || teamId == null) return;
                    this.picks[id] = teamId;
                    this.normalize();
                },

                // Drop any pick whose chosen team is no longer a valid participant.
                normalize() {
                    ['R16', 'QF', 'SF', 'THIRD', 'FINAL'].forEach(r => {
                        this.matchesInRound(r).forEach(m => {
                            const [a, b] = this.participants(m.id);
                            const p = this.picks[m.id];
                            if (p == null) return;
                            if (a == null || b == null || (p !== a && p !== b)) delete this.picks[m.id];
                        });
                    });
                },

                completedCount() {
                    return this.matches.filter(m => this.picks[m.id] != null).length;
                },

                isComplete() {
                    return this.completedCount() === this.matches.length
                        && this.finalScoreValid();
                },
            };
        }
    </script>
</x-app-layout>
