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
import EventRegulationSummary from '@/components/EventRegulationSummary.vue';
import GameEventResults from '@/components/GameEventResults.vue';
import Heading from '@/components/Heading.vue';
import HelpHint from '@/components/HelpHint.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useGameEventPresentation } from '@/composables/useGameEventPresentation';
import { useI18n } from '@/composables/useI18n';
import { index, show as eventShow } from '@/routes/game-events';
import { isGroupRing } from '@/lib/gameEventEntries';
import type { GameEventEntryProps } from '@/types/game-event';

const props = defineProps<GameEventEntryProps>();
const { t, number } = useI18n();
const { date } = useGameEventPresentation();
const ownEntry = computed(() => props.entry ?? props.event.ownEntry ?? null);
const groupRing = computed(() => isGroupRing(props.event.regulation?.format));
const groupDescription = computed(() =>
    props.event.discipline === 'couple'
        ? 'One male + one female of the same breed and owner'
        : props.event.discipline === 'breeders'
          ? '3–5 dogs of one breed from the same breeder or kennel'
          : 'Parent + 3–5 direct offspring',
);
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
                            groupRing
                                ? 'Enter the dogs and their group before the same show deadline. Final admission is checked after their individual assessments.'
                                : event.discipline === 'progeny'
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
            <EventRegulationSummary :regulation="event.regulation" />
            <template v-if="groupRing">
                <p v-if="event.linkedShow" class="event-note">
                    {{ t('Linked exhibition') }}:
                    <Link
                        :href="eventShow(event.linkedShow.id)"
                        class="text-link"
                        >{{ event.linkedShow.name }}</Link
                    >
                </p>
                <ol class="event-title-list">
                    <li>
                        <span
                            >1. {{ t('Individual entries') }} —
                            {{
                                t('Enter every member in the linked show.')
                            }}</span
                        >
                    </li>
                    <li>
                        <span
                            >2. {{ t(groupDescription) }} —
                            {{
                                t('Save the group before the common deadline.')
                            }}</span
                        >
                    </li>
                    <li>
                        <span
                            >3. {{ t('Breed selection → final ring') }} —
                            {{
                                t(
                                    'Only eligible groups enter the ring after individual assessments.',
                                )
                            }}</span
                        >
                    </li>
                </ol>
            </template>
            <div
                v-else-if="event.groupContests?.length"
                class="event-title-list"
            >
                <p
                    v-for="contest in event.groupContests"
                    :key="contest.id"
                    class="event-note"
                >
                    <Link :href="eventShow(contest.id)" class="text-link">{{
                        t(`events.discipline.${contest.discipline}`)
                    }}</Link>
                    ·
                    {{
                        t(
                            'Enter every member here first, then save the group before the common deadline.',
                        )
                    }}
                </p>
            </div>
            <p v-else-if="event.progenyContest" class="event-note">
                <Link
                    :href="eventShow(event.progenyContest.id)"
                    class="text-link"
                    >{{ t('Producer group ring') }}</Link
                >
                ·
                {{
                    t(
                        'Enter the parent and offspring here first, then save their group.',
                    )
                }}
            </p>
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
                                t(
                                    groupRing
                                        ? '{humans} entered groups'
                                        : event.discipline === 'conformation'
                                          ? '{humans} entered dogs · {clubs} club dogs'
                                          : '{humans} players · {clubs} club dogs',
                                    {
                                        humans: number(event.humanCount),
                                        clubs: number(event.clubCount),
                                    },
                                )
                            "
                        />
                    </dd>
                </div>
            </dl>
            <div
                v-if="event.regulation?.format !== 'odor_test'"
                class="event-prizes"
            >
                <span v-for="(prize, position) in event.prizes" :key="position"
                    ><Trophy :size="16" aria-hidden="true" />{{
                        t('Place {rank}: {amount} coins', {
                            rank: number(position + 1),
                            amount: number(prize),
                        })
                    }}</span
                >
            </div>
            <p v-else class="event-note">
                {{
                    t(
                        'Odor tests record a qualification without a placement or coin prize.',
                    )
                }}
            </p>
        </SurfaceCard>
        <nav
            v-if="ownEntries && ownEntries.length > 1"
            class="event-plan-presets"
            :aria-label="t('Your show entries')"
        >
            <Button
                v-for="entry in ownEntries"
                :key="entry.id"
                as-child
                variant="outline"
                size="sm"
                ><Link
                    :href="eventShow(event.id, { query: { pet: entry.petId } })"
                    :aria-current="
                        entry.id === ownEntry?.id ? 'page' : undefined
                    "
                    >{{ entry.name }}</Link
                ></Button
            >
        </nav>

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
                    :own-entries="ownEntries"
                    :selected-pet-id="selectedPetId"
                    :selected-entry-option-key="selectedEntryOptionKey"
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
                :own-entries="ownEntries"
                :selected-pet-id="selectedPetId"
                :selected-entry-option-key="selectedEntryOptionKey"
            />
            <GameEventResults :event="event" :entry="ownEntry" />
        </template>
    </div>
</template>
