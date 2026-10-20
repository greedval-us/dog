<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    CalendarDays,
    Check,
    ChevronDown,
    Circle,
    FileCheck,
} from '@lucide/vue';
import { computed, shallowRef, useId } from 'vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { show } from '@/routes/game-events';
import type {
    PetTitleCredential,
    PetTitleGoal,
} from '@/types/pet-title-progress';

const props = defineProps<{ goal: PetTitleGoal }>();
const { t, number, locale } = useI18n();
const pathId = useId();
const selectedKey = shallowRef<string | null>(null);
const selectedPath = computed(
    () =>
        props.goal.paths.find((path) => path.key === selectedKey.value) ??
        props.goal.paths.find((path) => path.recommended) ??
        props.goal.paths[0],
);
const selectedPathKey = computed({
    get: () => selectedPath.value?.key ?? '',
    set: (value: string) => {
        selectedKey.value = value;
    },
});
const statusLabel = computed(
    () =>
        ({
            achieved: 'Title earned',
            ready: 'Requirements met',
            in_progress: 'In progress',
            blocked: 'Conditions missing',
            unavailable: 'Currently unavailable',
        })[props.goal.status],
);
const dateFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            timeZone: 'Europe/Moscow',
        }),
);
const eventFormatter = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Europe/Moscow',
        }),
);
const credentialStatus = (status: PetTitleCredential['status']) =>
    ({
        counted: 'Counted for this path',
        available: 'Available document',
        used: 'Already exchanged',
        expired: 'Expired',
        ineligible: 'Does not meet this path',
    })[status];
</script>

<template>
    <article
        class="pet-title-goal"
        :class="'is-' + goal.status"
        :aria-label="goal.name"
    >
        <header class="pet-title-goal-heading">
            <div>
                <h4><Award :size="19" aria-hidden="true" />{{ goal.name }}</h4>
                <p class="pet-career-hint">
                    {{ t(`events.discipline.${goal.discipline}`)
                    }}<template v-if="goal.scopeLabel">
                        · {{ goal.scopeLabel }}</template
                    >
                </p>
            </div>
            <span class="pet-title-status">{{ t(statusLabel) }}</span>
        </header>
        <p class="pet-title-goal-summary">{{ goal.summary }}</p>
        <p v-if="goal.earnedCount" class="pet-career-hint">
            {{ t('Earned {count} times', { count: number(goal.earnedCount) })
            }}<template v-if="goal.repeatable">
                · {{ t('Can be earned again') }}</template
            >
        </p>
        <template v-if="selectedPath">
            <FormField
                v-if="goal.paths.length > 1"
                :id="pathId"
                :label="t('Title route')"
                v-slot="{ field }"
            >
                <select
                    v-bind="field"
                    v-model="selectedPathKey"
                    class="events-select"
                >
                    <option
                        v-for="path in goal.paths"
                        :key="path.key"
                        :value="path.key"
                    >
                        {{ path.label }}
                    </option>
                </select>
            </FormField>
            <p v-else class="pet-title-path-label">{{ selectedPath.label }}</p>
            <div
                class="pet-title-requirements"
                :aria-label="t('Title requirements')"
            >
                <h5>{{ t('What is still needed') }}</h5>
                <p v-if="selectedPath.ready" class="pet-title-ready">
                    <Check :size="17" aria-hidden="true" />{{
                        t('All conditions for this route are met.')
                    }}
                </p>
                <ul>
                    <li
                        v-for="requirement in selectedPath.requirements"
                        :key="requirement.key"
                        :class="{ 'is-met': requirement.met }"
                    >
                        <component
                            :is="requirement.met ? Check : Circle"
                            :size="16"
                            aria-hidden="true"
                        />
                        <div>
                            <span class="sr-only"
                                >{{
                                    t(
                                        requirement.met
                                            ? 'Condition met'
                                            : 'Condition not met',
                                    )
                                }}:
                            </span>
                            <strong>{{ requirement.label }}</strong>
                            <p v-if="requirement.note" class="pet-career-hint">
                                {{ requirement.note }}
                            </p>
                        </div>
                        <span
                            v-if="requirement.target > 1"
                            class="pet-title-requirement-count"
                            >{{ number(requirement.current) }} /
                            {{ number(requirement.target) }}</span
                        >
                    </li>
                </ul>
            </div>
            <details class="pet-title-documents">
                <summary>
                    <FileCheck :size="17" aria-hidden="true" />{{
                        t('Documents for this route')
                    }}<span>{{ number(selectedPath.credentials.length) }}</span
                    ><ChevronDown
                        :size="17"
                        class="pet-title-chevron"
                        aria-hidden="true"
                    />
                </summary>
                <ul v-if="selectedPath.credentials.length">
                    <li
                        v-for="credential in selectedPath.credentials"
                        :key="credential.id"
                        :class="'is-' + credential.status"
                    >
                        <div>
                            <strong>{{ credential.name }}</strong>
                            <p class="pet-title-document-status">
                                {{ t(credentialStatus(credential.status)) }} ·
                                <time :datetime="credential.awardedAt">{{
                                    dateFormatter.format(
                                        new Date(credential.awardedAt),
                                    )
                                }}</time>
                            </p>
                            <p v-if="credential.reason" class="pet-career-hint">
                                {{ credential.reason }}
                            </p>
                        </div>
                        <Link
                            v-if="credential.eventId !== null"
                            :href="show(credential.eventId)"
                            class="text-link"
                            >{{ t('View result')
                            }}<ArrowRight :size="14" aria-hidden="true"
                        /></Link>
                    </li>
                </ul>
                <p v-else class="pet-career-hint">
                    {{ t('No documents for this route yet.') }}
                </p>
            </details>
            <section
                v-if="
                    !selectedPath.ready &&
                    (goal.status !== 'achieved' || goal.repeatable)
                "
                class="pet-title-event-section"
                :aria-label="t('Where to earn the missing documents')"
            >
                <h5>
                    <CalendarDays :size="17" aria-hidden="true" />{{
                        t('Where to earn the missing documents')
                    }}
                </h5>
                <ul v-if="selectedPath.events.length" class="pet-title-events">
                    <li
                        v-for="event in selectedPath.events"
                        :key="`${event.id}:${event.classLabel}`"
                    >
                        <div>
                            <strong>{{ event.name }}</strong>
                            <p class="pet-career-hint">
                                <time :datetime="event.startsAt">{{
                                    eventFormatter.format(
                                        new Date(event.startsAt),
                                    )
                                }}</time>
                                · {{ event.classLabel }}
                            </p>
                            <p class="pet-title-event-contribution">
                                {{ event.contribution }}
                            </p>
                            <p class="pet-career-hint">
                                {{
                                    t('Registration closes: {date}', {
                                        date: eventFormatter.format(
                                            new Date(event.closesAt),
                                        ),
                                    })
                                }}
                            </p>
                            <p
                                v-if="event.registered"
                                class="pet-title-registered"
                            >
                                {{ t('Already registered') }}
                            </p>
                            <p v-if="event.reason" class="pet-career-hint">
                                {{ event.reason }}
                            </p>
                        </div>
                        <Button
                            v-if="event.eligible || event.registered"
                            as-child
                            variant="secondary"
                            size="sm"
                            ><Link :href="show(event.id)"
                                >{{
                                    t(
                                        event.registered
                                            ? 'View entry'
                                            : 'Open event',
                                    )
                                }}<ArrowRight
                                    :size="15"
                                    aria-hidden="true" /></Link
                        ></Button>
                        <Link v-else :href="show(event.id)" class="text-link"
                            >{{ t('View requirements')
                            }}<ArrowRight :size="14" aria-hidden="true"
                        /></Link>
                    </li>
                </ul>
                <p v-else class="pet-career-hint">
                    {{
                        t(
                            'Suitable events are not currently available. Check the requirements above.',
                        )
                    }}
                </p>
            </section>
            <ul v-if="selectedPath.notes.length" class="pet-title-notes">
                <li v-for="note in selectedPath.notes" :key="note">
                    {{ note }}
                </li>
            </ul>
        </template>
    </article>
</template>
