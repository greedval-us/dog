<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import {
    Brain,
    Dumbbell,
    Gauge,
    HeartHandshake,
    PawPrint,
    Wind,
} from '@lucide/vue';
import PetMetric from '@/components/PetMetric.vue';
import { statLabels } from '@/lib/petLabels';
import type { DogStat } from '@/types/pet';

withDefaults(
    defineProps<{
        values: Record<DogStat, number | { value: number; potential: number }>;
        variant?: 'tiles' | 'bars' | 'compact';
    }>(),
    { variant: 'tiles' },
);
const icons = {
    endurance: PawPrint,
    speed: Gauge,
    strength: Dumbbell,
    agility: Wind,
    obedience: HeartHandshake,
    intelligence: Brain,
};
const { t, number } = useI18n();
</script>

<template>
    <div
        v-if="variant !== 'tiles'"
        class="pet-metrics pet-stat-metrics"
        :class="{ 'pet-stats-compact': variant === 'compact' }"
    >
        <PetMetric
            v-for="(label, key) in statLabels"
            :key="key"
            :label="t(label)"
            :icon="icons[key]"
            tone="violet"
            show-maximum
            :value="
                typeof values[key] === 'number'
                    ? values[key]
                    : values[key].value
            "
            :maximum="
                typeof values[key] === 'number'
                    ? values[key]
                    : values[key].potential
            "
        />
    </div>
    <dl v-else class="dog-stats">
        <div v-for="(label, key) in statLabels" :key="key">
            <dt>{{ t(label) }}</dt>
            <dd v-if="typeof values[key] === 'number'">
                {{ number(values[key]) }}
            </dd>
            <dd v-else>
                {{ number(values[key].value) }}
                <span>/ {{ number(values[key].potential) }}</span>
            </dd>
        </div>
    </dl>
</template>
