<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Layers,
    Package,
    ShoppingBag,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CategoryTabs from '@/components/CategoryTabs.vue';
import CursorPagination from '@/components/CursorPagination.vue';
import Heading from '@/components/Heading.vue';
import ItemCharacteristics from '@/components/ItemCharacteristics.vue';
import ItemArtwork from '@/components/ItemArtwork.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { index } from '@/routes/inventory';
import { show as playerCard } from '@/routes/players';
import { index as shop } from '@/routes/shop';
import type { InventoryItem } from '@/types/inventory';
import type { ShopCategory } from '@/types/shop';

const props = defineProps<{
    categories: ShopCategory[];
    items: InventoryItem[];
    selectedCategory: number | null;
    inventoryCount: number;
    itemTypesCount: number;
    nextCursor: string | null;
    previousCursor: string | null;
}>();
const { t, locale, number } = useI18n();
const page = usePage();
const loading = ref(false);
const date = (value: string) =>
    new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(
        new Date(`${value}T12:00:00`),
    );
const categoryName = computed(
    () =>
        props.categories.find(
            (category) => category.id === props.selectedCategory,
        )?.name ?? t('All my items'),
);
const categoryLink = (category: number | null) =>
    index.url({ query: { category } });
const pageLink = (cursor: string) =>
    index.url({ query: { category: props.selectedCategory, cursor } });
</script>

<template>
    <div class="inventory-page">
        <Head :title="t('Inventory')" />
        <Link
            class="inventory-back"
            :href="playerCard(page.props.auth.user.username)"
            ><ArrowLeft :size="16" aria-hidden="true" />{{
                t('Player card')
            }}</Link
        >
        <div class="shop-heading">
            <Heading
                :title="t('Inventory')"
                :description="
                    t('Everything you have collected for your friend.')
                "
            />
            <Button as-child variant="secondary"
                ><Link :href="shop()"
                    ><ShoppingBag :size="18" aria-hidden="true" />{{
                        t('Visit the shop')
                    }}<ArrowRight :size="17" aria-hidden="true" /></Link
            ></Button>
        </div>

        <div class="inventory-overview">
            <span class="inventory-overview-icon"
                ><Package :size="32" :stroke-width="1.5" aria-hidden="true"
            /></span>
            <div class="inventory-overview-copy">
                <h2>{{ t('A place for little treasures') }}</h2>
                <p>
                    {{
                        t('Your purchases are here, ready for the days ahead.')
                    }}
                </p>
            </div>
            <dl class="inventory-totals">
                <div>
                    <dt>{{ t('Items') }}</dt>
                    <dd>{{ number(inventoryCount) }}</dd>
                </div>
                <div>
                    <dt>{{ t('Item types') }}</dt>
                    <dd>{{ number(itemTypesCount) }}</dd>
                </div>
            </dl>
        </div>

        <CategoryTabs
            v-if="categories.length"
            :categories="categories"
            :selected-category="selectedCategory"
            :category-link="categoryLink"
            :label="t('Inventory categories')"
            :all-label="t('All my items')"
            :disabled="loading"
            @start="loading = true"
            @finish="loading = false"
        />

        <section
            class="inventory-collection"
            :aria-label="categoryName"
            :aria-busy="loading"
        >
            <div class="shop-section-heading">
                <h2>{{ categoryName }}</h2>
                <span aria-live="polite">{{
                    loading
                        ? t('Loading…')
                        : t('Shown: {count}', { count: number(items.length) })
                }}</span>
            </div>
            <div v-if="items.length" class="inventory-grid">
                <article
                    v-for="item in items"
                    :key="item.id"
                    class="inventory-item"
                >
                    <div class="shop-item-image">
                        <ItemArtwork :category="item.categoryCode" /><span
                            v-if="!item.competition"
                            class="shop-quality"
                            >{{ t('Quality') }}
                            {{ number(item.quality) }}/10</span
                        >
                    </div>
                    <div class="inventory-item-body">
                        <span class="shop-item-category">{{
                            item.category
                        }}</span>
                        <h3 class="shop-item-name">{{ item.name }}</h3>
                        <span class="inventory-instance">{{
                            t('Item #{number}', { number: number(item.id) })
                        }}</span>
                        <div class="inventory-durability">
                            <div>
                                <label :for="`item-uses-${item.id}`">{{
                                    t('Uses remaining')
                                }}</label
                                ><strong
                                    >{{ number(item.remainingUses) }} /
                                    {{ number(item.usageLimit) }}</strong
                                >
                            </div>
                            <progress
                                :id="`item-uses-${item.id}`"
                                :value="item.remainingUses"
                                :max="item.usageLimit"
                            />
                        </div>
                        <p v-if="item.acquiredAt" class="inventory-acquired">
                            {{
                                t('Acquired on {date}', {
                                    date: date(item.acquiredAt),
                                })
                            }}
                        </p>
                        <details
                            v-if="
                                Object.keys(item.characteristics).length ||
                                item.competition
                            "
                            class="inventory-characteristics"
                        >
                            <summary>
                                {{ t('Item characteristics')
                                }}<Layers :size="16" aria-hidden="true" />
                            </summary>
                            <ItemCharacteristics
                                :characteristics="item.characteristics"
                                :bonuses="item.bonuses"
                                :granted-effects="item.grantedEffects"
                                :risks="item.risks"
                                :competition="item.competition"
                            />
                        </details>
                    </div>
                </article>
            </div>
            <EmptyState
                v-else
                class="inventory-empty"
                :kicker="null"
                :title="
                    inventoryCount === 0
                        ? t('Your inventory is waiting for its first item')
                        : t('No items on this page')
                "
                :description="
                    inventoryCount === 0
                        ? t(
                              'Choose a treat, a toy or something useful in the shop. Your purchases will appear here.',
                          )
                        : t('Return to all items to see your collection.')
                "
            >
                <template #icon><Package :stroke-width="1.25" /></template>
                <Button as-child
                    ><Link :href="inventoryCount === 0 ? shop() : index()"
                        >{{
                            inventoryCount === 0
                                ? t('Choose something for your dog')
                                : t('All my items')
                        }}<ArrowRight :size="17" aria-hidden="true" /></Link
                ></Button>
            </EmptyState>
            <CursorPagination
                :previous-cursor="previousCursor"
                :next-cursor="nextCursor"
                :page-link="pageLink"
                :label="t('Inventory pages')"
                :disabled="loading"
                @start="loading = true"
                @finish="loading = false"
            />
            <p v-if="inventoryCount" class="inventory-note">
                <Package :size="17" aria-hidden="true" />{{
                    t(
                        'Care items can be used in Quick actions on your dog’s page.',
                    )
                }}
            </p>
        </section>
    </div>
</template>
