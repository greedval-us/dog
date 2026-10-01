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
import PetQuickActions from '@/components/PetQuickActions.vue';
import PetSkills from '@/components/PetSkills.vue';
import { Deferred, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import SurfaceCard from '@/components/SurfaceCard.vue';
import StatusEffects from '@/components/StatusEffects.vue';
import { useI18n } from '@/composables/useI18n';
import type { PlayerPet } from '@/types/pet';
import type { PetCare } from '@/types/pet-care';
import type { PetSkills as PetSkillsData } from '@/types/pet-skill';

defineProps<{
    pet: PlayerPet;
    care: PetCare | null;
    skills: PetSkillsData | null;
}>();
const { t } = useI18n();
const tabs = [
    { value: 'overview', label: 'Overview', icon: PawPrint },
    { value: 'attributes', label: 'Development', icon: TrendingUp },
    {
        value: 'skills',
        label: 'Skills',
        icon: Sparkles,
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
        <TabsContent value="attributes" class="pet-tab-panel pet-development">
            <SurfaceCard
                :title="t('Main attributes')"
                :description="t('Current value / genetic potential.')"
                class="pet-attribute-details"
            >
                <DogStats :values="pet.stats" variant="compact" />
                <div class="pet-development-retention">
                    <p>
                        {{
                            t(
                                'Keep practicing: attributes slowly decrease over time. Item effects help retain progress.',
                            )
                        }}
                    </p>
                    <StatusEffects
                        v-if="care"
                        :effects="[...care.buffs, ...care.debuffs]"
                        :server-now="care.serverNow"
                        compact
                    />
                </div>
            </SurfaceCard>
            <Deferred data="care">
                <template #fallback
                    ><div class="dashboard-loading" role="status">
                        {{ t('Loading...') }}
                    </div></template
                >
                <template #rescue="{ reloading }"
                    ><div role="alert">
                        <p>{{ t('Could not load data. Please retry.') }}</p>
                        <Button
                            :disabled="reloading"
                            @click="router.reload({ only: ['care'] })"
                            >{{ t('Retry') }}</Button
                        >
                    </div></template
                >
                <PetQuickActions
                    v-if="care"
                    :pet="pet"
                    :care="care"
                    training-only
                />
            </Deferred>
        </TabsContent>
        <TabsContent value="skills" class="pet-tab-panel">
            <Deferred data="skills">
                <template #fallback
                    ><div class="dashboard-loading" role="status">
                        {{ t('Loading...') }}
                    </div></template
                >
                <template #rescue="{ reloading }"
                    ><div role="alert">
                        <p>{{ t('Could not load data. Please retry.') }}</p>
                        <Button
                            :disabled="reloading"
                            @click="router.reload({ only: ['pet', 'skills'] })"
                            >{{ t('Retry') }}</Button
                        >
                    </div></template
                >
                <PetSkills
                    v-if="skills"
                    :key="pet.id"
                    :pet="pet"
                    :data="skills"
                />
            </Deferred>
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
