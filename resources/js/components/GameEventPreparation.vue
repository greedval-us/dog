<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    ChevronDown,
    HeartPulse,
    GitBranch,
    RefreshCw,
} from '@lucide/vue';
import { computed } from 'vue';
import HelpHint from '@/components/HelpHint.vue';
import { Button } from '@/components/ui/button';
import { useGameEventPresentation } from '@/composables/useGameEventPresentation';
import { useI18n } from '@/composables/useI18n';
import { dashboard } from '@/routes';
import { pedigree } from '@/routes/pets';
import type { EventDiscipline, EventDog } from '@/types/game-event';

const props = defineProps<{
    dog: EventDog;
    discipline: EventDiscipline;
    closesAt: string;
    updatedAt: string;
    refreshing: boolean;
    disabled: boolean;
}>();
defineEmits<{ refresh: [] }>();
const { t, number } = useI18n();
const { date, decimal } = useGameEventPresentation();
const preparation = computed(() => props.dog.preparation);
const stateLabels: Record<string, string> = {
    energy: 'Energy',
    health: 'Health',
    hydration: 'Hydration',
    satiety: 'Satiety',
    mood: 'Mood',
    bond: 'Bond',
    cleanliness: 'Cleanliness',
};
const statLabels: Record<string, string> = {
    speed: 'Speed',
    agility: 'Agility',
    endurance: 'Endurance',
    strength: 'Strength',
    obedience: 'Obedience',
    intelligence: 'Intelligence',
};
const relevantStats = computed(() => [
    ...new Set(
        preparation.value?.stages.flatMap((stage) =>
            Object.keys(stage.weights),
        ) ?? [],
    ),
]);
const careStates = computed(() =>
    Object.entries(preparation.value?.states ?? {}).filter(([key]) =>
        [
            'energy',
            'health',
            'hydration',
            'satiety',
            'mood',
            'bond',
            ...(props.discipline === 'conformation' ? ['cleanliness'] : []),
        ].includes(key),
    ),
);
const careToRestore = computed(() =>
    careStates.value
        .filter(([, value]) => value < 50)
        .map(([key]) => t(stateLabels[key] ?? key))
        .join(', '),
);
</script>

<template>
    <section
        v-if="preparation"
        class="event-readiness"
        :aria-label="t('Preparation at a glance')"
    >
        <div class="event-readiness-heading">
            <span class="event-note">{{
                t('Updated: {time}', { time: date(updatedAt) })
            }}</span>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                :disabled="refreshing || disabled"
                :aria-busy="refreshing"
                @click="$emit('refresh')"
                ><RefreshCw
                    :size="15"
                    aria-hidden="true"
                    :class="{ 'busy-spinner': refreshing }"
                />{{
                    t(refreshing ? 'Refreshing…' : 'Refresh preparation')
                }}</Button
            >
        </div>
        <div class="event-readiness-heading">
            <div class="surface-heading-help">
                <Activity :size="18" aria-hidden="true" />
                <h3>{{ t('Preparation at a glance') }}</h3>
                <HelpHint
                    :text="
                        t(
                            discipline === 'progeny'
                                ? 'The parent’s care, tactics and equipment do not affect this documentary evaluation.'
                                : 'Energy, health, hydration and satiety affect stage quality. Mood, bond and obedience support focus. These values are not a chance of winning.',
                        )
                    "
                />
            </div>
            <span class="event-badge">{{ preparation.divisionLabel }}</span>
        </div>
        <ul
            v-if="preparation.blockingReasons.length"
            class="event-readiness-blockers"
            role="status"
        >
            <li v-for="reason in preparation.blockingReasons" :key="reason">
                {{ reason }}
            </li>
        </ul>
        <template v-if="discipline !== 'progeny'">
            <dl class="event-readiness-metrics">
                <div>
                    <dt>{{ t('Care effect on quality') }}</dt>
                    <dd>× {{ decimal(preparation.careMultiplier) }}</dd>
                </div>
                <div>
                    <dt>{{ t('Starting focus') }}</dt>
                    <dd>{{ decimal(preparation.initialFocus) }} / 100</dd>
                </div>
                <div>
                    <dt>{{ t('Starting fatigue') }}</dt>
                    <dd>{{ decimal(preparation.initialFatigue) }} / 100</dd>
                </div>
            </dl>
            <p class="event-note">{{ t('Before equipment and tactics') }}</p>
            <div class="event-state-chips">
                <span
                    v-for="[key, value] in careStates"
                    :key="key"
                    :class="{ 'needs-care': value < 50 }"
                    >{{ t(stateLabels[key] ?? key) }}
                    <strong>{{ number(Math.round(value)) }}%</strong></span
                >
            </div>
            <p
                v-if="careToRestore && !preparation.blockingReasons.length"
                class="event-note"
            >
                {{
                    t('Before the start: restore {states}.', {
                        states: careToRestore,
                    })
                }}
            </p>
            <p class="event-note">
                {{
                    t('Condition is locked at {time}.', {
                        time: date(closesAt),
                    })
                }}
            </p>
        </template>
        <p v-else class="event-note">
            {{
                t(
                    'The parent’s care, tactics and equipment do not affect this documentary evaluation.',
                )
            }}
        </p>
        <details class="event-explanation">
            <summary>
                <GitBranch :size="16" aria-hidden="true" />{{
                    t('Attributes and inheritance')
                }}<ChevronDown :size="16" aria-hidden="true" />
            </summary>
            <p class="event-note">
                {{
                    t(
                        'Inherited exterior matters in shows. Inherited potential sets training limits. A pedigree document or a parent’s titles do not add a performance bonus.',
                    )
                }}
            </p>
            <dl
                v-if="discipline !== 'progeny' && relevantStats.length"
                class="event-attribute-grid"
            >
                <div v-for="key in relevantStats" :key="key">
                    <dt>{{ t(statLabels[key] ?? key) }}</dt>
                    <dd>
                        {{
                            t('Training: {current} / {potential}', {
                                current: number(preparation.stats[key] ?? 0),
                                potential: number(
                                    preparation.potentials[key] ?? 0,
                                ),
                            })
                        }}<small>{{
                            t('Performance contribution: {value}/100', {
                                value: decimal(
                                    preparation.normalizedStats[key] ?? 0,
                                ),
                            })
                        }}</small>
                    </dd>
                </div>
            </dl>
            <HelpHint
                v-if="relevantStats.length"
                :text="
                    t(
                        'Training increases performance with diminishing returns: each additional attribute point contributes a little less. Potential is the training ceiling, not a separate score bonus.',
                    )
                "
                :label="t('Performance contribution')"
            />
            <p class="event-note">
                {{
                    t('Generation {generation} · known parents: {count}/2', {
                        generation: number(preparation.pedigree.generation),
                        count: number(preparation.pedigree.knownParents),
                    })
                }}
            </p>
            <Link :href="pedigree(dog.id)" class="text-link">{{
                t('View pedigree')
            }}</Link>
        </details>
        <Link
            v-if="discipline !== 'progeny'"
            :href="dashboard({ query: { pet: dog.id } })"
            class="text-link event-care-link"
            ><HeartPulse :size="16" aria-hidden="true" />{{
                t('Care and training')
            }}</Link
        >
    </section>
</template>
