<script setup lang="ts">
import { PawPrint } from '@lucide/vue';
import DogStats from '@/components/DogStats.vue';
import DogCompetitionCredentials from '@/components/DogCompetitionCredentials.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { useI18n } from '@/composables/useI18n';
import { useBreedingMessages } from '@/composables/useBreedingMessages';
import type { Puppy } from '@/types/breeding';

defineProps<{ puppy: Puppy }>();
const { t, number } = useI18n();
const { date } = useBreedingMessages();
</script>

<template>
    <SurfaceCard class="puppy-card">
        <div class="breeding-parent-identity">
            <span class="breeding-paw"
                ><PawPrint :size="32" aria-hidden="true"
            /></span>
            <div>
                <h2>{{ puppy.name }}</h2>
                <p>{{ puppy.breed }}</p>
            </div>
        </div>
        <div class="breeding-tags">
            <span>{{ t(puppy.sex === 'male' ? 'Male' : 'Female') }}</span
            ><span>{{ puppy.coatLabel }}</span
            ><span>{{
                t('Generation {number}', { number: number(puppy.generation) })
            }}</span>
        </div>
        <div v-if="puppy.status !== 'kennel'" class="puppy-deadline">
            <span>{{ t('Decision deadline') }}</span
            ><time :datetime="puppy.expiresAt">{{
                date(puppy.expiresAt)
            }}</time>
        </div>
        <p v-else class="field-hint">
            {{ t('Waiting for a home at the kennel') }}
        </p>
        <DogCompetitionCredentials :exterior="puppy.exterior" />
        <div
            v-if="
                puppy.parentTitles?.father.length ||
                puppy.parentTitles?.mother.length
            "
            class="puppy-parent-titles"
        >
            <DogCompetitionCredentials
                :titles="puppy.parentTitles?.father"
                title-label="Father’s titles"
            />
            <DogCompetitionCredentials
                :titles="puppy.parentTitles?.mother"
                title-label="Mother’s titles"
            />
            <p class="field-hint">
                {{
                    t(
                        'Parent achievements add to the pedigree’s reputation. The puppy’s conformation is its own inherited quality.',
                    )
                }}
            </p>
        </div>
        <details class="puppy-potential">
            <summary>{{ t('Genetic potential') }}</summary>
            <DogStats :values="puppy.potentials" />
            <p class="field-hint">
                {{
                    t(
                        'Starting characteristics are 20% of genetic potential. Your puppy’s active life begins when it goes home.',
                    )
                }}
            </p>
        </details>
        <slot />
    </SurfaceCard>
</template>
