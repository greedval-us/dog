<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ChevronDown,
    Clock,
    Coins,
    Trophy,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import GameEventEntryForm from '@/components/GameEventEntryForm.vue';
import GameEventResults from '@/components/GameEventResults.vue';
import Heading from '@/components/Heading.vue';
import HelpHint from '@/components/HelpHint.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useGameEventPresentation } from '@/composables/useGameEventPresentation';
import { useI18n } from '@/composables/useI18n';
import { index } from '@/routes/game-events';
import type { GameEventEntryProps } from '@/types/game-event';

const props = defineProps<GameEventEntryProps>();
const { t, number } = useI18n();
const { date } = useGameEventPresentation();
const ownEntry = computed(() => props.entry ?? props.event.ownEntry ?? null);
</script>

<template>
    <div class="events-page">
        <Head :title="t(`events.discipline.${event.discipline}`)" />
        <div class="events-page-heading">
            <div class="page-heading-with-help">
                <Heading
                    :title="t(`events.discipline.${event.discipline}`)"
                    :description="t(`events.frequency.${event.frequency}`)"
                />
                <HelpHint
                    :text="
                        t(
                            event.discipline === 'progeny'
                                ? 'Choose the offspring group before registration closes. It will be evaluated at the scheduled time, even when you are offline.'
                                : 'Register before the deadline and save your plan. Your dog performs at the scheduled time, even when you are offline.',
                        )
                    "
                />
            </div>
            <Button as-child variant="secondary"
                ><Link :href="index()"
                    ><ArrowLeft :size="17" aria-hidden="true" />{{
                        t('Event calendar')
                    }}</Link
                ></Button
            >
        </div>
        <SurfaceCard class="event-overview">
            <div class="event-card-heading">
                <span class="event-badge">{{
                    t(`events.status.${event.status}`)
                }}</span
                ><span>{{
                    t('All event times are shown in Moscow time.')
                }}</span>
            </div>
            <dl class="event-overview-facts">
                <div>
                    <dt>
                        <CalendarDays :size="17" aria-hidden="true" />{{
                            t('Registration opens')
                        }}
                    </dt>
                    <dd>
                        <time :datetime="event.opensAt">{{
                            date(event.opensAt)
                        }}</time>
                    </dd>
                </div>
                <div>
                    <dt>
                        <Clock :size="17" aria-hidden="true" />{{ t('Start') }}
                    </dt>
                    <dd>
                        <time :datetime="event.startsAt">{{
                            date(event.startsAt)
                        }}</time>
                    </dd>
                </div>
                <div>
                    <dt>
                        <Clock :size="17" aria-hidden="true" />{{
                            t('Registration closes')
                        }}
                    </dt>
                    <dd>
                        <time :datetime="event.closesAt">{{
                            date(event.closesAt)
                        }}</time>
                    </dd>
                </div>
                <div>
                    <dt>
                        <Coins :size="17" aria-hidden="true" />{{
                            t('Entry fee')
                        }}
                    </dt>
                    <dd>
                        {{ t('{amount} coins', { amount: number(event.fee) }) }}
                    </dd>
                </div>
                <div>
                    <dt>
                        <Users :size="17" aria-hidden="true" />{{
                            t('Entries')
                        }}
                    </dt>
                    <dd>
                        {{ number(event.entryCount)
                        }}<HelpHint
                            :text="
                                t('{humans} players · {clubs} club dogs', {
                                    humans: number(event.humanCount),
                                    clubs: number(event.clubCount),
                                })
                            "
                        />
                    </dd>
                </div>
            </dl>
            <div class="event-prizes">
                <span v-for="(prize, position) in event.prizes" :key="position"
                    ><Trophy :size="16" aria-hidden="true" />{{
                        t('Place {rank}: {amount} coins', {
                            rank: number(position + 1),
                            amount: number(prize),
                        })
                    }}</span
                >
            </div>
        </SurfaceCard>

        <template v-if="event.status !== 'scheduled'">
            <GameEventResults :event="event" :entry="ownEntry" />
            <details class="event-explanation event-saved-entry">
                <summary>
                    {{ t('Saved entry and rules') }}
                    <ChevronDown :size="16" aria-hidden="true" />
                </summary>
                <GameEventEntryForm
                    :event="event"
                    :server-now="serverNow"
                    :dogs="dogs"
                    :equipment="equipment"
                    :entry="ownEntry"
                />
            </details>
        </template>
        <template v-else>
            <GameEventEntryForm
                :event="event"
                :server-now="serverNow"
                :dogs="dogs"
                :equipment="equipment"
                :entry="ownEntry"
            />
            <GameEventResults :event="event" :entry="ownEntry" />
        </template>
    </div>
</template>
