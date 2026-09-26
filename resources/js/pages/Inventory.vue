<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Layers,
    Package,
    ShoppingBag,
    SlidersHorizontal,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
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
const { t, locale } = useI18n();
const page = usePage();
const loading = ref(false);
const number = (value: number) =>
    new Intl.NumberFormat(locale.value).format(value);
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
const pageLink = (cursor: string) =>
    index({ query: { category: props.selectedCategory, cursor } });
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

        <nav
            v-if="categories.length"
            class="shop-categories"
            :aria-label="t('Inventory categories')"
            :inert="loading"
        >
            <Link
                :href="index()"
                class="shop-category"
                :class="{ 'is-active': selectedCategory === null }"
                :aria-current="selectedCategory === null ? 'page' : undefined"
                preserve-scroll
                @start="loading = true"
                @finish="loading = false"
                ><SlidersHorizontal :size="16" aria-hidden="true" />{{
                    t('All my items')
                }}</Link
            >
            <Link
                v-for="category in categories"
                :key="category.id"
                :href="index({ query: { category: category.id } })"
                class="shop-category"
                :class="{ 'is-active': selectedCategory === category.id }"
                :aria-current="
                    selectedCategory === category.id ? 'page' : undefined
                "
                preserve-scroll
                @start="loading = true"
                @finish="loading = false"
                >{{ category.name }}</Link
            >
        </nav>

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
                            v-if="Object.keys(item.characteristics).length"
                            class="inventory-characteristics"
                        >
                            <summary>
                                {{ t('Item characteristics')
                                }}<Layers :size="16" aria-hidden="true" />
                            </summary>
                            <dl class="shop-properties">
                                <div
                                    v-for="(value, key) in item.characteristics"
                                    :key="key"
                                >
                                    <dt>{{ t(key) }}</dt>
                                    <dd>
                                        {{
                                            typeof value === 'number'
                                                ? number(value)
                                                : t(String(value))
                                        }}
                                    </dd>
                                </div>
                            </dl>
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
            <nav
                v-if="previousCursor || nextCursor"
                class="shop-pagination"
                :aria-label="t('Inventory pages')"
                :inert="loading"
            >
                <Button v-if="previousCursor" as-child variant="outline"
                    ><Link
                        :href="pageLink(previousCursor)"
                        preserve-scroll
                        @start="loading = true"
                        @finish="loading = false"
                        ><ArrowLeft :size="17" aria-hidden="true" />{{
                            t('Previous')
                        }}</Link
                    ></Button
                >
                <Button v-if="nextCursor" as-child variant="outline"
                    ><Link
                        :href="pageLink(nextCursor)"
                        preserve-scroll
                        @start="loading = true"
                        @finish="loading = false"
                        >{{ t('Next')
                        }}<ArrowRight :size="17" aria-hidden="true" /></Link
                ></Button>
            </nav>
            <p v-if="inventoryCount" class="inventory-note">
                <Package :size="17" aria-hidden="true" />{{
                    t(
                        'Each item keeps its own uses. Using and equipping items will be available later.',
                    )
                }}
            </p>
        </section>
    </div>
</template>
