<script setup lang="ts">
import {
    Backpack,
    BookOpen,
    GitBranch,
    History,
    PawPrint,
    Sparkles,
    TrendingUp,
} from '@lucide/vue';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import DogStats from '@/components/DogStats.vue';
import PetFeaturePlaceholder from '@/components/PetFeaturePlaceholder.vue';
import PetOverview from '@/components/PetOverview.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { useI18n } from '@/composables/useI18n';
import type { PlayerPet } from '@/types/pet';
import type { PetCare } from '@/types/pet-care';

defineProps<{ pet: PlayerPet; care: PetCare | null }>();
const { t } = useI18n();
const tabs = [
    { value: 'overview', label: 'Overview', icon: PawPrint },
    { value: 'attributes', label: 'Attributes', icon: TrendingUp },
    {
        value: 'skills',
        label: 'Skills',
        icon: Sparkles,
        description:
            'Commands, learned skills and training progress will appear here.',
    },
    {
        value: 'equipment',
        label: 'Equipment',
        icon: Backpack,
        description:
            'Collars, accessories and equipped items will appear here.',
    },
    {
        value: 'pedigree',
        label: 'Pedigree',
        icon: GitBranch,
        description:
            'Parents, ancestors and family connections will appear here.',
    },
    {
        value: 'offspring',
        label: 'Offspring',
        icon: BookOpen,
        description: 'Puppies and breeding results will appear here.',
    },
    {
        value: 'history',
        label: 'History',
        icon: History,
        description: 'Milestones and shared memories will appear here.',
    },
] as const;
</script>

<template>
    <TabsRoot default-value="overview" class="pet-details">
        <TabsList class="pet-tabs" :aria-label="t('Pet sections')">
            <TabsTrigger
                v-for="tab in tabs"
                :key="tab.value"
                :value="tab.value"
                class="pet-tab"
            >
                <component :is="tab.icon" :size="17" />{{ t(tab.label) }}
                <span
                    v-if="'description' in tab"
                    class="pet-tab-planned"
                    :aria-label="t('Soon')"
                    :title="t('Soon')"
                ></span>
            </TabsTrigger>
        </TabsList>
        <TabsContent value="overview" class="pet-tab-panel"
            ><PetOverview :pet="pet" :care="care"
        /></TabsContent>
        <TabsContent value="attributes" class="pet-tab-panel">
            <SurfaceCard
                :title="t('Main attributes')"
                :description="t('Current value / genetic potential.')"
                class="pet-attribute-details"
            >
                <DogStats :values="pet.stats" variant="bars" />
                <p class="pet-feature-note">
                    {{
                        t('Training will become available in a future update.')
                    }}
                </p>
            </SurfaceCard>
        </TabsContent>
        <template v-for="tab in tabs" :key="tab.value">
            <TabsContent
                v-if="'description' in tab"
                :value="tab.value"
                class="pet-tab-panel"
            >
                <PetFeaturePlaceholder
                    :title="t(tab.label)"
                    :description="t(tab.description)"
                    :icon="tab.icon"
                />
            </TabsContent>
        </template>
    </TabsRoot>
</template>
