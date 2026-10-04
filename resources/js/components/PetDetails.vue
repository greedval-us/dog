<script setup lang="ts">
import {
    BookOpen,
    GitBranch,
    History,
    PawPrint,
    Sparkles,
    Trophy,
    TrendingUp,
} from '@lucide/vue';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import DogStats from '@/components/DogStats.vue';
import PetHistory from '@/components/PetHistory.vue';
import PetOverview from '@/components/PetOverview.vue';
import PetQuickActions from '@/components/PetQuickActions.vue';
import PetSkills from '@/components/PetSkills.vue';
import PetCareer from '@/components/PetCareer.vue';
import { ref } from 'vue';
import { Deferred, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import SurfaceCard from '@/components/SurfaceCard.vue';
import StatusEffects from '@/components/StatusEffects.vue';
import { useI18n } from '@/composables/useI18n';
import type { PlayerPet } from '@/types/pet';
import type { PetCare } from '@/types/pet-care';
import type { PetSkills as PetSkillsData } from '@/types/pet-skill';
import type { PetCareer as PetCareerData } from '@/types/pet-career';
import { index as breeding } from '@/routes/breeding';
import { index as puppies } from '@/routes/puppies';
import { pedigree } from '@/routes/pets';

defineProps<{
    pet: PlayerPet;
    care: PetCare | null;
    skills: PetSkillsData | null;
    career?: PetCareerData | null;
}>();
const { t } = useI18n();
const selectedTab = ref('overview');
const tabs = [
    { value: 'overview', label: 'Overview', icon: PawPrint },
    { value: 'attributes', label: 'Development', icon: TrendingUp },
    {
        value: 'skills',
        label: 'Skills',
        icon: Sparkles,
    },
    {
        value: 'titles',
        label: 'Titles',
        icon: Trophy,
    },
    {
        value: 'pedigree',
        label: 'Pedigree',
        icon: GitBranch,
    },
    {
        value: 'offspring',
        label: 'Offspring',
        icon: BookOpen,
    },
    {
        value: 'history',
        label: 'History',
        icon: History,
    },
] as const;
</script>

<template>
    <TabsRoot v-model="selectedTab" class="pet-details">
        <TabsList class="pet-tabs" :aria-label="t('Pet sections')">
            <TabsTrigger
                v-for="tab in tabs"
                :key="tab.value"
                :value="tab.value"
                class="pet-tab"
            >
                <component :is="tab.icon" :size="17" />{{ t(tab.label) }}
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
        <TabsContent value="history" class="pet-tab-panel">
            <PetHistory :key="pet.id" :pet="pet" />
        </TabsContent>
        <TabsContent value="titles" class="pet-tab-panel">
            <PetCareer :pet-id="pet.id" :career="career" />
        </TabsContent>
        <TabsContent value="offspring" class="pet-tab-panel">
            <SurfaceCard
                :title="t('Offspring')"
                :description="
                    t(
                        'View this dog’s puppies or choose a partner for a new litter.',
                    )
                "
            >
                <div class="breeding-links">
                    <Button as-child
                        ><Link :href="puppies({ query: { parent: pet.id } })">{{
                            t('View offspring')
                        }}</Link></Button
                    ><Button as-child variant="secondary"
                        ><Link :href="breeding({ query: { pet: pet.id } })">{{
                            t('Choose a breeding partner')
                        }}</Link></Button
                    >
                </div>
            </SurfaceCard>
        </TabsContent>
        <TabsContent value="pedigree" class="pet-tab-panel">
            <SurfaceCard
                :title="t('Pedigree')"
                :description="
                    t(
                        pet.hasPedigree
                            ? 'Follow the family branches and select a dog to see its card and attributes.'
                            : 'There are no parent records for this dog. A pedigree becomes available for dogs born through breeding.',
                    )
                "
            >
                <Button v-if="pet.hasPedigree" as-child>
                    <Link :href="pedigree(pet.id)"
                        ><GitBranch :size="17" aria-hidden="true" />{{
                            t('View pedigree')
                        }}</Link
                    >
                </Button>
            </SurfaceCard>
        </TabsContent>
    </TabsRoot>
</template>
