<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PawPrint } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useGameEventPresentation } from '@/composables/useGameEventPresentation';
import { useI18n } from '@/composables/useI18n';
import {
    entryWasWithdrawn,
    groupEventEntries,
    isExhibition,
} from '@/lib/gameEventEntries';
import { show as dogProfile } from '@/routes/pets';
import type { GameEventDetail, GameEventEntry } from '@/types/game-event';

const props = defineProps<{
    event: GameEventDetail;
    entry: GameEventEntry | null;
}>();
const { t, number } = useI18n();
const { decimal, stageLabel, optionLabel } = useGameEventPresentation();
const ownEntry = computed(() => props.entry);
const isShow = computed(() => isExhibition(props.event.discipline));
const entriesByDivision = computed(() =>
    groupEventEntries(props.event.entries),
);
const divisionLabel = (division: string, entries: GameEventEntry[]) => {
    if (entries[0]?.divisionLabel) return entries[0].divisionLabel;
    const [tier, group] = division.split(':');
    return [
        t(`events.division.${tier}`),
        group && !group.startsWith('breed-')
            ? t(`events.division.${group}`)
            : null,
    ]
        .filter(Boolean)
        .join(' · ');
};
const replayId = ref<number | null>(
    ownEntry.value?.result ? ownEntry.value.id : null,
);
const replay = computed(
    () =>
        props.event.entries.find((entry) => entry.id === replayId.value) ??
        (ownEntry.value?.id === replayId.value ? ownEntry.value : null),
);
const replayWithdrawn = computed(() => entryWasWithdrawn(replay.value));

watch(
    () => ownEntry.value?.result,
    (result) => {
        if (result && ownEntry.value) replayId.value = ownEntry.value.id;
    },
);
</script>

<template>
    <div class="event-outcomes">
        <SurfaceCard
            :title="
                t(event.status === 'completed' ? 'Results' : 'Participants')
            "
            class="event-results"
        >
            <p v-if="!event.entries.length" class="event-note">
                {{
                    t(
                        'Be the first to enter. Club dogs will join before the start if places remain.',
                    )
                }}
            </p>
            <section
                v-for="[division, entries] in entriesByDivision"
                :key="division"
                class="event-division"
            >
                <h3>
                    {{
                        t('Division: {name}', {
                            name: divisionLabel(division, entries),
                        })
                    }}
                </h3>
                <div class="event-table-scroll">
                    <table class="event-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ t('Place') }}</th>
                                <th scope="col">{{ t('Dog') }}</th>
                                <th scope="col">{{ t('Result') }}</th>
                                <th scope="col">{{ t('Reward') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="participant in entries"
                                :key="participant.id"
                                :class="{
                                    'is-own': participant.id === ownEntry?.id,
                                }"
                            >
                                <td>
                                    {{
                                        participant.rank === null
                                            ? '—'
                                            : number(participant.rank)
                                    }}
                                </td>
                                <td>
                                    <Link
                                        v-if="
                                            participant.petId &&
                                            !participant.isNpc
                                        "
                                        :href="dogProfile(participant.petId)"
                                        class="text-link"
                                        >{{ participant.name }}</Link
                                    ><strong v-else>{{
                                        participant.name
                                    }}</strong
                                    ><small
                                        >{{
                                            participant.isNpc
                                                ? t('Club dog (NPC)')
                                                : participant.ownerName
                                        }}{{
                                            participant.id === ownEntry?.id
                                                ? ` · ${t('Your dog')}`
                                                : ''
                                        }}</small
                                    >
                                </td>
                                <td>
                                    <template v-if="participant.result"
                                        ><span
                                            v-if="
                                                entryWasWithdrawn(participant)
                                            "
                                            >{{ t('Withdrawn') }}</span
                                        ><span
                                            v-else-if="
                                                participant.result.eliminated
                                            "
                                            >{{ t('Eliminated') }}</span
                                        ><template v-else
                                            ><span v-if="isShow">{{
                                                t('Score: {score}', {
                                                    score: decimal(
                                                        participant.result
                                                            .score,
                                                    ),
                                                })
                                            }}</span
                                            ><span v-else>{{
                                                t(
                                                    '{time} s · {count} penalties',
                                                    {
                                                        time: decimal(
                                                            participant.result
                                                                .time,
                                                        ),
                                                        count: number(
                                                            participant.result
                                                                .penalties,
                                                        ),
                                                    },
                                                )
                                            }}</span></template
                                        ><Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="replayId = participant.id"
                                            >{{
                                                t(
                                                    entryWasWithdrawn(
                                                        participant,
                                                    )
                                                        ? 'View withdrawal reason'
                                                        : event.discipline ===
                                                            'progeny'
                                                          ? 'View evaluation'
                                                          : 'View replay',
                                                )
                                            }}</Button
                                        ></template
                                    ><span v-else>{{
                                        t('Awaiting start')
                                    }}</span>
                                </td>
                                <td>
                                    {{
                                        participant.prize > 0
                                            ? t('{amount} coins', {
                                                  amount: number(
                                                      participant.prize,
                                                  ),
                                              })
                                            : '—'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </SurfaceCard>
        <SurfaceCard
            v-if="replay?.result"
            :title="
                t(
                    replayWithdrawn
                        ? 'Entry withdrawn — {name}'
                        : event.discipline === 'progeny'
                          ? 'Progeny evaluation — {name}'
                          : 'Performance replay — {name}',
                    { name: replay.name },
                )
            "
            class="event-replay"
        >
            <p
                v-if="replayWithdrawn && replay.result.reason"
                class="event-note"
            >
                {{ replay.result.reason }}
            </p>
            <p v-else-if="!replayWithdrawn" class="event-note">
                {{
                    t(
                        event.discipline === 'progeny'
                            ? 'The evaluation shows how the selected offspring scored against each judging criterion.'
                            : 'The replay shows your saved decisions, mistakes and condition after every stage.',
                    )
                }}
            </p>
            <ol
                v-if="!replayWithdrawn && replay.result.stages.length"
                class="event-replay-stages"
            >
                <li
                    v-for="(stage, stageIndex) in replay.result.stages"
                    :key="stage.key"
                >
                    <span class="event-replay-number">{{
                        number(stageIndex + 1)
                    }}</span>
                    <div class="event-replay-body">
                        <h3>{{ stageLabel(stage.key) }}</h3>
                        <strong v-if="event.discipline !== 'progeny'">{{
                            optionLabel(
                                event.discipline,
                                stage.key,
                                stage.decision,
                            )
                        }}</strong>
                        <p>{{ t(stage.reason) }}</p>
                        <dl class="event-replay-metrics">
                            <div v-if="event.discipline !== 'progeny'">
                                <dt>{{ t('Time') }}</dt>
                                <dd>
                                    {{
                                        t('{time} seconds', {
                                            time: decimal(stage.time),
                                        })
                                    }}
                                </dd>
                            </div>
                            <div v-if="event.discipline !== 'progeny'">
                                <dt>{{ t('Penalties') }}</dt>
                                <dd>{{ number(stage.penalties) }}</dd>
                            </div>
                            <div>
                                <dt>{{ t('Score') }}</dt>
                                <dd>{{ decimal(stage.score) }}</dd>
                            </div>
                            <div v-if="event.discipline !== 'progeny'">
                                <dt>{{ t('Fatigue') }}</dt>
                                <dd>{{ decimal(stage.fatigue) }}</dd>
                            </div>
                            <div v-if="event.discipline !== 'progeny'">
                                <dt>{{ t('Focus') }}</dt>
                                <dd>{{ decimal(stage.focus) }}</dd>
                            </div>
                        </dl>
                    </div>
                </li>
            </ol>
            <div class="event-replay-total">
                <PawPrint :size="18" aria-hidden="true" />{{
                    replayWithdrawn
                        ? t('Withdrawn')
                        : replay.result.eliminated
                          ? t('Eliminated')
                          : isShow
                            ? t('Final score: {score}', {
                                  score: decimal(replay.result.score),
                              })
                            : t('Final result: {time} s · {count} penalties', {
                                  time: decimal(replay.result.time),
                                  count: number(replay.result.penalties),
                              })
                }}
            </div>
        </SurfaceCard>
    </div>
</template>
