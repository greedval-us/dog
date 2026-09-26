<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRight, Check, Coins, Package, ShoppingBag } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import CategoryTabs from '@/components/CategoryTabs.vue';
import CursorPagination from '@/components/CursorPagination.vue';
import Heading from '@/components/Heading.vue';
import ItemArtwork from '@/components/ItemArtwork.vue';
import ShopPurchasePanel from '@/components/ShopPurchasePanel.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import { index, store } from '@/routes/shop';
import { index as inventory } from '@/routes/inventory';
import type { ShopCategory, ShopOffer } from '@/types/shop';

const props = defineProps<{
    categories: ShopCategory[];
    offers: ShopOffer[];
    selectedCategory: number | null;
    purchaseToken: string;
    inventoryCount: number;
    nextCursor: string | null;
    previousCursor: string | null;
}>();
const { t, number } = useI18n();
const selectedId = ref<number | null>(props.offers[0]?.id ?? null);
const selectedOffer = computed(
    () =>
        props.offers.find((offer) => offer.id === selectedId.value) ??
        props.offers[0],
);
const categoryName = computed(
    () =>
        props.categories.find(
            (category) => category.id === props.selectedCategory,
        )?.name ?? t('All items'),
);
const compact = useMediaQuery('(max-width: 900px)', { ssrWidth: 1440 });
const detailsOpen = ref(false);
const selectedButton = ref<HTMLElement | null>(null);
const page = usePage();
const purchaseForm = useForm({ purchase_token: props.purchaseToken });
const purchasing = computed(() => purchaseForm.processing);
const purchaseError = computed(() =>
    Object.values(purchaseForm.errors).join(' '),
);
const purchasedId = ref<number | null>(null);
watch(
    () => selectedOffer.value?.id,
    () => purchaseForm.clearErrors(),
);
const loading = ref(false);
const categoryLink = (category: number | null) =>
    index.url({ query: { category } });
const pageLink = (cursor: string) =>
    index.url({ query: { category: props.selectedCategory, cursor } });

function select(offer: ShopOffer, event: MouseEvent) {
    if (purchasing.value) return;
    selectedButton.value = event.currentTarget as HTMLElement;
    selectedId.value = offer.id;
    detailsOpen.value = true;
}

function toggleDetails(open: boolean) {
    if (!purchasing.value) detailsOpen.value = open;
}

function restoreFocus(event: Event) {
    event.preventDefault();
    selectedButton.value?.focus();
}

function buy() {
    const offer = selectedOffer.value;
    if (
        !offer ||
        purchasing.value ||
        Number(page.props.auth.user.coins) < offer.price
    )
        return;
    purchaseForm
        .transform(({ purchase_token }) => ({
            offer_id: offer.id,
            item_id: offer.itemId,
            expected_currency: offer.currency,
            expected_price: offer.price,
            purchase_token,
        }))
        .submit(store(), {
            preserveScroll: true,
            onSuccess: () => {
                purchasedId.value = offer.id;
                purchaseForm.purchase_token = props.purchaseToken;
            },
        });
}
</script>

<template>
    <div class="shop-page">
        <Head :title="t('Shop')" />
        <div class="shop-heading">
            <Heading
                :title="t('Shop')"
                :description="t('Little things for a happy dog life.')"
            />
            <Link :href="inventory()" class="shop-inventory-count"
                ><Package :size="20" aria-hidden="true" />{{
                    t('Items in inventory: {count}', {
                        count: number(inventoryCount),
                    })
                }}</Link
            >
        </div>

        <div class="shop-intro">
            <span class="shop-intro-icon"
                ><ShoppingBag :size="29" :stroke-width="1.5" aria-hidden="true"
            /></span>
            <div>
                <h2>{{ t('A little care, every day') }}</h2>
                <p>
                    {{
                        t('Food, toys and everyday essentials for your friend.')
                    }}
                </p>
            </div>
            <span class="shop-intro-caption">{{
                t('Purchases with coins only')
            }}</span>
        </div>

        <CategoryTabs
            :categories="categories"
            :selected-category="selectedCategory"
            :category-link="categoryLink"
            :label="t('Item categories')"
            :all-label="t('All items')"
            :disabled="purchasing || loading"
            @start="loading = true"
            @finish="loading = false"
        />

        <div class="shop-layout" :aria-busy="loading">
            <section class="shop-catalogue" :aria-label="categoryName">
                <div class="shop-section-heading">
                    <h2>{{ categoryName }}</h2>
                    <span aria-live="polite">{{
                        loading
                            ? t('Loading…')
                            : t('Shown: {count}', {
                                  count: number(offers.length),
                              })
                    }}</span>
                </div>
                <div v-if="offers.length" class="shop-grid">
                    <button
                        v-for="offer in offers"
                        :key="offer.id"
                        type="button"
                        class="shop-item"
                        :class="{
                            'is-selected': selectedOffer?.id === offer.id,
                        }"
                        :aria-pressed="selectedOffer?.id === offer.id"
                        :aria-label="t('View {name}', { name: offer.name })"
                        :disabled="purchasing || loading"
                        @click="select(offer, $event)"
                    >
                        <span class="shop-item-image">
                            <ItemArtwork :category="offer.categoryCode" />
                            <span class="shop-quality"
                                >{{ t('Quality') }}
                                {{ number(offer.quality) }}/10</span
                            >
                            <span
                                v-if="selectedOffer?.id === offer.id"
                                class="shop-item-check"
                                ><Check :size="17" aria-hidden="true"
                            /></span>
                        </span>
                        <span class="shop-item-body">
                            <span class="shop-item-category">{{
                                offer.category
                            }}</span>
                            <strong class="shop-item-name">{{
                                offer.name
                            }}</strong>
                            <span class="shop-item-uses"
                                >{{
                                    t('Uses: {count}', {
                                        count: number(offer.usageLimit),
                                    })
                                }}<span
                                    v-if="offer.owned > 0"
                                    class="shop-item-owned"
                                    >{{
                                        t('Owned: {count}', {
                                            count: number(offer.owned),
                                        })
                                    }}</span
                                ></span
                            >
                            <span class="shop-item-bottom"
                                ><strong class="shop-item-price"
                                    ><Coins
                                        class="coin-icon"
                                        :size="20"
                                        aria-hidden="true"
                                    />{{
                                        t('{amount} coins', {
                                            amount: number(offer.price),
                                        })
                                    }}</strong
                                ><span class="shop-view"
                                    >{{ t('Details')
                                    }}<ArrowRight
                                        :size="16"
                                        aria-hidden="true" /></span
                            ></span>
                        </span>
                    </button>
                </div>
                <SurfaceCard v-else class="shop-empty">
                    <Package
                        :size="42"
                        :stroke-width="1.25"
                        aria-hidden="true"
                    />
                    <h3>{{ t('No items here yet') }}</h3>
                    <p>{{ t('Check another category or come back later.') }}</p>
                    <Button
                        v-if="selectedCategory !== null"
                        as-child
                        variant="outline"
                        ><Link :href="index()">{{
                            t('All items')
                        }}</Link></Button
                    >
                </SurfaceCard>
                <CursorPagination
                    :previous-cursor="previousCursor"
                    :next-cursor="nextCursor"
                    :page-link="pageLink"
                    :label="t('Catalogue pages')"
                    :disabled="purchasing || loading"
                    @start="loading = true"
                    @finish="loading = false"
                />
            </section>

            <aside
                v-if="!compact && selectedOffer"
                class="shop-detail"
                :aria-label="t('Selected item')"
            >
                <SurfaceCard :title="t('Selected item')">
                    <ShopPurchasePanel
                        :offer="selectedOffer"
                        :processing="purchasing"
                        :purchased="
                            purchaseForm.recentlySuccessful &&
                            purchasedId === selectedOffer.id
                        "
                        :error="purchaseError"
                        @buy="buy"
                    />
                </SurfaceCard>
            </aside>
        </div>

        <Dialog
            v-if="compact && selectedOffer"
            :open="detailsOpen"
            @update:open="toggleDetails"
        >
            <DialogContent
                class="shop-dialog"
                @close-auto-focus="restoreFocus"
                :show-close-button="!purchasing"
                @escape-key-down="purchasing && $event.preventDefault()"
                @interact-outside="purchasing && $event.preventDefault()"
            >
                <DialogHeader
                    ><DialogTitle>{{ t('Selected item') }}</DialogTitle
                    ><DialogDescription class="sr-only">{{
                        selectedOffer.name
                    }}</DialogDescription></DialogHeader
                >
                <ShopPurchasePanel
                    :offer="selectedOffer"
                    :processing="purchasing"
                    :purchased="
                        purchaseForm.recentlySuccessful &&
                        purchasedId === selectedOffer.id
                    "
                    :error="purchaseError"
                    @buy="buy"
                />
            </DialogContent>
        </Dialog>
    </div>
</template>
