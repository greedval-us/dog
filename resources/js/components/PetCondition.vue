<script setup lang="ts">
import {
    BatteryCharging,
    Droplets,
    Heart,
    Smile,
    Soup,
    Sparkles,
    HandHeart,
} from '@lucide/vue';
import PetMetric from '@/components/PetMetric.vue';
import HelpHint from '@/components/HelpHint.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { useI18n } from '@/composables/useI18n';
import { stateLabels } from '@/lib/petLabels';
import type { PlayerPet } from '@/types/pet';

withDefaults(
    defineProps<{ states: PlayerPet['states']; readOnly?: boolean }>(),
    { readOnly: false },
);
const { t } = useI18n();
const metrics = [
    { key: 'health', icon: Heart, tone: 'sage' },
    { key: 'energy', icon: BatteryCharging, tone: 'amber' },
    { key: 'satiety', icon: Soup, tone: 'sage' },
    { key: 'hydration', icon: Droplets, tone: 'blue' },
    { key: 'mood', icon: Smile, tone: 'sage' },
    { key: 'cleanliness', icon: Sparkles, tone: 'sage' },
    { key: 'bond', icon: HandHeart, tone: 'rose' },
] as const;
</script>

<template>
    <SurfaceCard
        :title="t(readOnly ? 'Preserved wellbeing' : 'Wellbeing')"
        class="pet-condition"
    >
        <template #header>
            <div class="surface-heading-help">
                <h2>{{ t(readOnly ? 'Preserved wellbeing' : 'Wellbeing') }}</h2>
                <HelpHint
                    :label="t(readOnly ? 'Preserved wellbeing' : 'Wellbeing')"
                    :text="
                        t(
                            readOnly
                                ? 'State preserved at departure.'
                                : 'Below 25% — needs care.',
                        )
                    "
                />
            </div>
        </template>
        <div class="pet-metrics">
            <PetMetric
                v-for="metric in metrics"
                :key="metric.key"
                :label="t(stateLabels[metric.key])"
                :value="states[metric.key]"
                :icon="metric.icon"
                :tone="metric.tone"
                :warn-when-low="!readOnly"
            />
        </div>
    </SurfaceCard>
</template>
