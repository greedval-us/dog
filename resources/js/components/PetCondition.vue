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
import SurfaceCard from '@/components/SurfaceCard.vue';
import { useI18n } from '@/composables/useI18n';
import { stateLabels } from '@/lib/petLabels';
import type { PlayerPet } from '@/types/pet';

defineProps<{ states: PlayerPet['states'] }>();
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
        :title="t('Wellbeing')"
        :description="t('Small steps to a happy dog.')"
        class="pet-condition"
    >
        <div class="pet-metrics">
            <PetMetric
                v-for="metric in metrics"
                :key="metric.key"
                :label="t(stateLabels[metric.key])"
                :value="states[metric.key]"
                :icon="metric.icon"
                :tone="metric.tone"
                warn-when-low
            />
        </div>
    </SurfaceCard>
</template>
