<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, Coins, Package, ShoppingBag } from '@lucide/vue';
import { computed, nextTick, useId, useTemplateRef, watch } from 'vue';
import ActionHint from '@/components/ActionHint.vue';
import InputError from '@/components/InputError.vue';
import ItemCharacteristics from '@/components/ItemCharacteristics.vue';
import ItemArtwork from '@/components/ItemArtwork.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { purchaseShortfall } from '@/lib/purchaseAvailability';
import { show as player } from '@/routes/players';
import type { ShopOffer } from '@/types/shop';

const props = defineProps<{
    offer: ShopOffer;
    processing: boolean;
    purchased: boolean;
    error: string;
}>();
const emit = defineEmits<{ buy: [] }>();
const { t, number } = useI18n();
const page = usePage();
const id = useId();
const balance = computed(() => Number(page.props.auth.user.coins));
const shortfall = computed(() =>
    purchaseShortfall(props.offer.price, balance.value),
);
const actionReason = computed(() =>
    props.offer.stock !== null && props.offer.stock < 1
        ? t('This item is out of stock. Choose another item.')
        : shortfall.value > 0
          ? t('You need {amount} more coins.', {
                amount: number(shortfall.value),
            })
          : null,
);
const errorSummary = useTemplateRef<HTMLDivElement>('errorSummary');
watch(
    () => props.error,
    async (message) => {
        if (message) {
            await nextTick();
            errorSummary.value?.focus();
        }
    },
);
</script>

<template>
    <form class="shop-purchase-panel" @submit.prevent="emit('buy')">
        <ItemArtwork :category="offer.categoryCode" />
        <div class="shop-product-heading">
            <span class="section-kicker">{{ offer.category }}</span>
            <h3>{{ offer.name }}</h3>
            <p>{{ offer.description }}</p>
        </div>
        <ItemCharacteristics
            :characteristics="offer.characteristics"
            :bonuses="offer.bonuses"
            :granted-effects="offer.grantedEffects"
            :risks="offer.risks"
        >
            <div>
                <dt>{{ t('Quality') }}</dt>
                <dd>{{ number(offer.quality) }} / 10</dd>
            </div>
            <div>
                <dt>{{ t('Uses per item') }}</dt>
                <dd>{{ number(offer.usageLimit) }}</dd>
            </div>
            <div v-if="offer.stock !== null">
                <dt>{{ t('In stock') }}</dt>
                <dd>{{ number(offer.stock) }}</dd>
            </div>
        </ItemCharacteristics>
        <p class="shop-owned">
            <Package :size="17" aria-hidden="true" />{{
                t('In your inventory: {count}', { count: number(offer.owned) })
            }}
        </p>
        <div class="shop-checkout">
            <div class="shop-price-row">
                <span>{{ t('Price per item') }}</span>
                <strong
                    ><Coins class="coin-icon" :size="23" aria-hidden="true" />{{
                        t('{amount} coins', { amount: number(offer.price) })
                    }}</strong
                >
            </div>
            <Button
                type="submit"
                class="shop-buy-button"
                :disabled="processing || Boolean(actionReason)"
                :aria-busy="processing"
                :aria-describedby="
                    actionReason ? id + '-purchase-reason' : undefined
                "
            >
                <ShoppingBag :size="18" aria-hidden="true" />{{
                    processing ? t('Purchasing…') : t('Buy item')
                }}
            </Button>
            <ActionHint :id="id + '-purchase-reason'" :message="actionReason">
                <Link
                    v-if="
                        shortfall > 0 &&
                        (offer.stock === null || offer.stock > 0)
                    "
                    :href="player(page.props.auth.user.username)"
                    class="text-link"
                    >{{ t('Earn coins at daily work') }}</Link
                >
            </ActionHint>
            <p v-if="purchased" class="shop-success" role="status">
                <Check :size="17" aria-hidden="true" />{{
                    t('Added to your inventory')
                }}
            </p>
            <div
                v-if="error"
                ref="errorSummary"
                tabindex="-1"
                class="shop-errors"
            >
                <InputError :message="error" />
                <Button
                    type="button"
                    variant="outline"
                    :disabled="processing"
                    @click="router.reload()"
                    >{{ t('Refresh shop') }}</Button
                >
            </div>
            <p class="shop-purchase-note">
                {{
                    t(
                        'Care items can be used in Quick actions on your dog’s page.',
                    )
                }}
            </p>
        </div>
    </form>
</template>
