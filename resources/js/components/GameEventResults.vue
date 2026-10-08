<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, PawPrint, Trophy } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import HelpHint from '@/components/HelpHint.vue';
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
import { show as eventShow } from '@/routes/game-events';
import type { GameEventDetail, GameEventEntry } from '@/types/game-event';

const props = defineProps<{
    event: GameEventDetail;
    entry: GameEventEntry | null;
}>();
const { t, number } = useI18n();
const { decimal, stageLabel, optionLabel, modifierEffect } =
    useGameEventPresentation();
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
const replayHeading = ref<HTMLElement | null>(null);
const replayTitle = computed(() =>
    replay.value
        ? t(
              replayWithdrawn.value
                  ? 'Entry withdrawn — {name}'
                  : props.event.discipline === 'progeny'
                    ? 'Progeny evaluation — {name}'
                    : 'Performance replay — {name}',
              { name: replay.value.name },
          )
        : '',
);

function replayActionLabel(participant: GameEventEntry): string {
    return t(
        entryWasWithdrawn(participant)
            ? 'View withdrawal reason'
            : props.event.discipline === 'progeny'
              ? 'View evaluation'
              : 'View replay',
    );
}

async function selectReplay(participant: GameEventEntry): Promise<void> {
    replayId.value = participant.id;
    await nextTick();
    replayHeading.value?.focus({ preventScroll: true });
    replayHeading.value?.scrollIntoView({
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'instant'
            : 'smooth',
        block: 'center',
    });
}

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
            v-if="ownEntry?.result"
            class="event-own-result"
            :title="t('Your result')"
        >
            <div class="event-own-result-heading">
                <Trophy :size="25" aria-hidden="true" />
                <strong>{{ ownEntry.name }}</strong>
                <span v-if="entryWasWithdrawn(ownEntry)">{{
                    t('Withdrawn')
                }}</span>
                <span v-else-if="ownEntry.result.eliminated">{{
                    t('Eliminated')
                }}</span>
                <span v-else-if="ownEntry.rank !== null">{{
                    t('Place {rank}', { rank: number(ownEntry.rank) })
                }}</span>
                <span v-if="ownEntry.prize > 0" class="event-badge">{{
                    t('{amount} coins', { amount: number(ownEntry.prize) })
                }}</span>
            </div>
            <p v-if="ownEntry.divisionLabel" class="event-note">
                {{ ownEntry.divisionLabel }}
            </p>
        </SurfaceCard>
        <SurfaceCard
            :title="
                t(event.status === 'completed' ? 'Results' : 'Participants')
            "
            class="event-results"
        >
            <nav
                v-if="event.divisions?.length"
                class="event-division-tabs"
                :aria-label="t('Competition divisions')"
            >
                <Link
                    v-for="division in event.divisions"
                    :key="division.key"
                    :href="
                        eventShow(event.id, {
                            query: { division: division.key },
                        })
                    "
                    :only="['event', 'entry', 'serverNow']"
                    preserve-scroll
                    preserve-state
                    :aria-current="
                        division.key === event.activeDivision
                            ? 'page'
                            : undefined
                    "
                    :class="{
                        'is-active': division.key === event.activeDivision,
                    }"
                    >{{ division.label
                    }}<small>{{
                        t('{humans} players · {clubs} club dogs', {
                            humans: number(division.humanCount),
                            clubs: number(division.clubCount),
                        })
                    }}</small></Link
                >
            </nav>
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
                                            :aria-label="
                                                replayActionLabel(participant) +
                                                ': ' +
                                                participant.name
                                            "
                                            @click="selectReplay(participant)"
                                            >{{
                                                replayActionLabel(participant)
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
        <SurfaceCard v-if="replay?.result" class="event-replay">
            <template #header>
                <h2 ref="replayHeading" data-slot="card-title" tabindex="-1">
                    {{ replayTitle }}
                </h2>
            </template>
            <p
                v-if="replayWithdrawn && replay.result.reason"
                class="event-note"
            >
                {{ replay.result.reason }}
            </p>
            <div
                v-else-if="!replayWithdrawn"
                class="surface-heading-help event-replay-intro"
            >
                <span>{{ t('What shaped the result') }}</span>
                <HelpHint
                    :text="
                        t(
                            event.discipline === 'progeny'
                                ? 'The evaluation shows how the selected offspring scored against each judging criterion.'
                                : 'The replay shows your saved decisions, mistakes and condition after every stage.',
                        )
                    "
                />
            </div>
            <div
                v-if="
                    !replayWithdrawn &&
                    replay.result.preparation &&
                    event.discipline !== 'progeny'
                "
                class="event-result-preparation"
            >
                <p class="event-note">
                    {{
                        t(
                            'Condition recorded when registration closed; later care does not change this result.',
                        )
                    }}
                </p>
                <dl class="event-readiness-metrics">
                    <div>
                        <dt>{{ t('Care effect on quality') }}</dt>
                        <dd>
                            ×
                            {{
                                decimal(
                                    replay.result.preparation.careMultiplier,
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt>{{ t('Starting focus') }}</dt>
                        <dd>
                            {{
                                decimal(replay.result.preparation.initialFocus)
                            }}
                            / 100
                        </dd>
                    </div>
                    <div>
                        <dt>{{ t('Starting fatigue') }}</dt>
                        <dd>
                            {{
                                decimal(
                                    replay.result.preparation.initialFatigue,
                                )
                            }}
                            / 100
                        </dd>
                    </div>
                </dl>
                <details class="event-explanation">
                    <summary>
                        {{ t('Recorded equipment effect')
                        }}<ChevronDown :size="16" aria-hidden="true" />
                    </summary>
                    <div class="event-gear-modifiers">
                        <span
                            v-for="(value, key) in replay.result.preparation
                                .modifiers"
                            :key="key"
                            >{{ modifierEffect(key, value) }}</span
                        >
                    </div>
                    <p class="event-note">
                        {{
                            t(
                                'Precision lowers the base error risk in percentage points. Focus adds starting focus points. Pace affects time; stamina reduces fatigue gained at each stage.',
                            )
                        }}
                    </p>
                    <p
                        v-if="event.discipline === 'conformation'"
                        class="event-note"
                    >
                        {{
                            t(
                                'For conformation, focus and precision help judging; stamina limits fatigue. Pace equipment does not increase the final score.',
                            )
                        }}
                    </p>
                </details>
            </div>
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
                        <details
                            v-if="
                                stage.factors && event.discipline !== 'progeny'
                            "
                            class="event-explanation event-stage-factors"
                        >
                            <summary>
                                {{ t('Stage factors')
                                }}<ChevronDown :size="16" aria-hidden="true" />
                            </summary>
                            <dl class="event-replay-metrics">
                                <div>
                                    <dt>{{ t('Stage quality') }}</dt>
                                    <dd>
                                        {{ decimal(stage.factors.quality) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt>{{ t('Error risk') }}</dt>
                                    <dd>
                                        {{
                                            decimal(
                                                stage.factors.mistakeChance *
                                                    100,
                                            )
                                        }}%
                                    </dd>
                                </div>
                                <div>
                                    <dt>{{ t('Starting focus') }}</dt>
                                    <dd>
                                        {{ decimal(stage.factors.startFocus) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt>{{ t('Starting fatigue') }}</dt>
                                    <dd>
                                        {{
                                            decimal(stage.factors.startFatigue)
                                        }}
                                    </dd>
                                </div>
                                <template
                                    v-if="stage.factors.exterior !== null"
                                >
                                    <div>
                                        <dt>
                                            {{ t('Exterior contribution') }}
                                        </dt>
                                        <dd>
                                            {{
                                                decimal(
                                                    stage.factors
                                                        .exteriorContribution,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>
                                            {{ t('Presentation contribution') }}
                                        </dt>
                                        <dd>
                                            {{
                                                decimal(
                                                    stage.factors
                                                        .presentationContribution,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </template>
                            </dl>
                            <p class="event-note">
                                {{
                                    t(
                                        'Error risk describes the stage calculation, not the chance of winning. Random mistakes and the other dogs also affect placement.',
                                    )
                                }}
                            </p>
                        </details>
                        <dl class="event-replay-metrics">
                            <div v-if="!isShow">
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
