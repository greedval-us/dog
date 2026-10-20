<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CursorPagination from '@/components/CursorPagination.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PetMarketOwnListing from '@/components/PetMarketOwnListing.vue';
import PetMarketPurchase from '@/components/PetMarketPurchase.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { index as market } from '@/routes/pet-market';
import { dashboard } from '@/routes';
import type { PetMarketPagination, PetSale } from '@/types/pet-market';

defineProps<{
    listings: PetSale[];
    ownListings: PetSale[];
    freeSlots: number;
    purchaseToken: string;
    pagination: PetMarketPagination;
}>();
const { t, number } = useI18n();
const pageLink = (cursor: string) => market.url({ query: { cursor } });
</script>

<template>
    <div class="pet-market-page">
        <Head :title="t('Dog market')" />
        <Heading
            :title="t('Dog market')"
            :description="
                t(
                    'Meet dogs offered by other players. Age, pedigree and awards stay with each dog.',
                )
            "
        />
        <p class="pet-market-places">
            {{ t('Free places: {count}', { count: number(freeSlots) }) }}
        </p>
        <section
            v-if="ownListings.length"
            class="pet-market-section"
            :aria-label="t('Your listings')"
        >
            <h2>{{ t('Your listings') }}</h2>
            <div class="pet-market-grid">
                <PetMarketOwnListing
                    v-for="listing in ownListings"
                    :key="listing.id"
                    :listing="listing"
                />
            </div>
        </section>
        <section
            class="pet-market-section"
            :aria-label="t('Dogs looking for a new home')"
        >
            <h2>{{ t('Dogs looking for a new home') }}</h2>
            <div v-if="listings.length" class="pet-market-grid">
                <PetMarketPurchase
                    v-for="listing in listings"
                    :key="listing.id"
                    :listing="listing"
                    :free-slots="freeSlots"
                    :purchase-token="purchaseToken"
                />
            </div>
            <EmptyState
                v-else
                :title="t('No dogs are listed right now.')"
                :description="
                    t('Check back later, or manage the dogs in your care.')
                "
            >
                <Button as-child variant="secondary"
                    ><Link :href="dashboard()">{{ t('My dog') }}</Link></Button
                >
            </EmptyState>
            <CursorPagination
                :previous-cursor="pagination.previousCursor"
                :next-cursor="pagination.nextCursor"
                :page-link="pageLink"
                :label="t('Dog market pages')"
                :disabled="false"
            />
        </section>
    </div>
</template>
