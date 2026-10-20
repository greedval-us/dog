<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DogCompetitionCredentials from '@/components/DogCompetitionCredentials.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { useI18n } from '@/composables/useI18n';
import { formatAgeMonths } from '@/lib/petAge';
import { show as player } from '@/routes/players';
import { show as dog } from '@/routes/pets';
import type { PetSale } from '@/types/pet-market';

defineProps<{ listing: PetSale }>();
const { t, number, locale } = useI18n();
</script>

<template>
    <SurfaceCard
        class="pet-market-card"
        :title="listing.pet.name"
        title-tag="h3"
        :description="listing.pet.breed"
    >
        <div class="pet-market-summary">
            <span>{{
                t('Age: {age}', {
                    age: formatAgeMonths(listing.pet.ageMonths, locale),
                })
            }}</span>
            <span>{{ t(listing.pet.sex === 'male' ? 'Male' : 'Female') }}</span>
            <span v-if="listing.pet.retired" class="pet-market-retired">{{
                t('Retired')
            }}</span>
        </div>
        <p class="field-hint">
            {{ t('Seller') }}:
            <Link :href="player(listing.seller.username)" class="text-link">{{
                listing.seller.username
            }}</Link>
        </p>
        <DogCompetitionCredentials :titles="listing.pet.titles" />
        <Link :href="dog(listing.pet.id)" class="text-link">{{
            t('View dog profile')
        }}</Link>
        <p class="pet-market-price">
            {{ t('{amount} coins', { amount: number(listing.price) }) }}
        </p>
        <slot />
    </SurfaceCard>
</template>
