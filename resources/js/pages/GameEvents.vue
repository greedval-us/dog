<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, Clock, Coins, Trophy, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { useGameEventPresentation } from '@/composables/useGameEventPresentation';
import { index, show } from '@/routes/game-events';
import type { GameEventFilters, GameEventSummary } from '@/types/game-event';

const props = defineProps<{
    events: GameEventSummary[];
    filters?: GameEventFilters;
    nextCursor?: string | null;
}>();
const { t, number } = useI18n();
const { date, calendarDay } = useGameEventPresentation();
const loading = ref(false);
const frequency = ref(props.filters?.frequency ?? '');
const kind = ref(props.filters?.kind ?? '');
const groupedEvents = computed(() => {
    const groups = new Map<string, GameEventSummary[]>();
    for (const event of props.events) {
        const key = calendarDay(event.startsAt);
        groups.set(key, [...(groups.get(key) ?? []), event]);
    }
    return [...groups.entries()];
});
function filter() {
    router.get(
        index({
            query: {
                frequency: frequency.value || null,
                kind: kind.value || null,
            },
        }),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                loading.value = true;
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="events-page">
        <Head :title="t('Events and shows')" />
        <Heading
            :title="t('Events and shows')"
            :description="
                t(
                    'Prepare your dog, choose your tactics and compete with other players.',
                )
            "
        />
        <SurfaceCard class="events-intro">
            <div class="events-intro-heading">
                <span class="events-icon"><Trophy aria-hidden="true" /></span>
                <div>
                    <h2>{{ t('Your next start') }}</h2>
                    <p>{{ t('All event times are shown in Moscow time.') }}</p>
                </div>
            </div>
            <p>
                {{
                    t(
                        'Register before the deadline and save your plan. Your dog performs at the scheduled time, even when you are offline.',
                    )
                }}
            </p>
            <p>
                {{
                    t(
                        'Daily starts build experience, weekly cups test consistency and monthly championships award prestigious titles.',
                    )
                }}
            </p>
            <p>
                {{
                    t(
                        'Club dogs fill empty places and are clearly marked in the results.',
                    )
                }}
            </p>
        </SurfaceCard>
        <form class="events-filters" @submit.prevent="filter">
            <FormField
                id="event-kind"
                :label="t('Event type')"
                v-slot="{ field }"
            >
                <select
                    v-bind="field"
                    v-model="kind"
                    class="events-select"
                    :disabled="loading"
                    @change="filter"
                >
                    <option value="">{{ t('All events') }}</option>
                    <option value="competition">{{ t('Competitions') }}</option>
                    <option value="exhibition">{{ t('Dog shows') }}</option>
                </select>
            </FormField>
            <FormField
                id="event-frequency"
                :label="t('Event frequency')"
                v-slot="{ field }"
            >
                <select
                    v-bind="field"
                    v-model="frequency"
                    class="events-select"
                    :disabled="loading"
                    @change="filter"
                >
                    <option value="">{{ t('All frequencies') }}</option>
                    <option value="daily">
                        {{ t('events.frequency.daily') }}
                    </option>
                    <option value="weekly">
                        {{ t('events.frequency.weekly') }}
                    </option>
                    <option value="monthly">
                        {{ t('events.frequency.monthly') }}
                    </option>
                </select>
            </FormField>
        </form>
        <section
            class="events-calendar"
            :aria-busy="loading"
            :aria-label="t('Event calendar')"
        >
            <div
                v-for="[day, dayEvents] in groupedEvents"
                :key="day"
                class="events-calendar-day"
            >
                <h2 class="events-day-heading">
                    <CalendarDays :size="20" aria-hidden="true" />{{ day }}
                </h2>
                <div class="events-grid">
                    <SurfaceCard
                        v-for="event in dayEvents"
                        :key="event.id"
                        class="event-card"
                    >
                        <div class="event-card-heading">
                            <span class="event-badge">{{
                                t(`events.frequency.${event.frequency}`)
                            }}</span
                            ><span class="event-status">{{
                                t(`events.status.${event.status}`)
                            }}</span>
                        </div>
                        <h3>
                            {{ t(`events.discipline.${event.discipline}`) }}
                        </h3>
                        <dl class="event-facts">
                            <div>
                                <dt>
                                    <Clock :size="16" aria-hidden="true" />{{
                                        t('Start')
                                    }}
                                </dt>
                                <dd>
                                    <time :datetime="event.startsAt">{{
                                        date(event.startsAt)
                                    }}</time>
                                </dd>
                            </div>
                            <div>
                                <dt>{{ t('Registration closes') }}</dt>
                                <dd>
                                    <time :datetime="event.closesAt">{{
                                        date(event.closesAt)
                                    }}</time>
                                </dd>
                            </div>
                            <div>
                                <dt>
                                    <Coins :size="16" aria-hidden="true" />{{
                                        t('Entry fee')
                                    }}
                                </dt>
                                <dd>
                                    {{
                                        t('{amount} coins', {
                                            amount: number(event.fee),
                                        })
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt>
                                    <Trophy :size="16" aria-hidden="true" />{{
                                        t('First prize')
                                    }}
                                </dt>
                                <dd>
                                    {{
                                        t('{amount} coins', {
                                            amount: number(
                                                event.prizes[0] ?? 0,
                                            ),
                                        })
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt>
                                    <Users :size="16" aria-hidden="true" />{{
                                        t('Entries')
                                    }}
                                </dt>
                                <dd>{{ number(event.entryCount) }}</dd>
                            </div>
                        </dl>
                        <Button
                            as-child
                            :variant="
                                event.canRegister ? 'default' : 'secondary'
                            "
                            ><Link :href="show(event.id)">{{
                                event.status === 'completed'
                                    ? t('View results')
                                    : event.canRegister
                                      ? t('Prepare for this event')
                                      : t('View event')
                            }}</Link></Button
                        >
                    </SurfaceCard>
                </div>
            </div>
            <SurfaceCard v-if="!events.length" class="events-empty"
                ><CalendarDays :size="36" aria-hidden="true" />
                <h2>{{ t('No events match these filters') }}</h2>
                <p>
                    {{ t('Try another frequency or event type.') }}
                </p></SurfaceCard
            >
            <Button v-if="nextCursor" as-child variant="outline"
                ><Link
                    :href="
                        index({
                            query: {
                                frequency: frequency || null,
                                kind: kind || null,
                                cursor: nextCursor,
                            },
                        })
                    "
                    >{{ t('More events') }}</Link
                ></Button
            >
        </section>
    </div>
</template>
