<script setup lang="ts">
import { PawPrint } from '@lucide/vue';
import DogStats from '@/components/DogStats.vue';
import DogCompetitionCredentials from '@/components/DogCompetitionCredentials.vue';
import { useI18n } from '@/composables/useI18n';
import type { BreedingParent } from '@/types/breeding';

defineProps<{ pet: BreedingParent; compact?: boolean }>();
const { t, number } = useI18n();
</script>

<template>
    <div class="breeding-parent">
        <div class="breeding-parent-identity">
            <span class="breeding-paw"
                ><PawPrint :size="28" aria-hidden="true"
            /></span>
            <div>
                <h3>{{ pet.name }}</h3>
                <p>{{ pet.breedName }}</p>
            </div>
        </div>
        <div class="breeding-tags">
            <span>{{ t(pet.sex === 'male' ? 'Male' : 'Female') }}</span>
            <span>{{ pet.coatColorLabel }}</span>
            <span>{{
                t('Generation {number}', { number: number(pet.generation) })
            }}</span>
        </div>
        <DogStats v-if="!compact" :values="pet.stats" variant="compact" />
        <DogCompetitionCredentials
            :exterior="pet.exterior"
            :titles="pet.titles"
            :compact="compact"
        />
        <slot />
    </div>
</template>
