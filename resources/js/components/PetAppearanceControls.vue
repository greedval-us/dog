<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import {
    Check,
    ChevronDown,
    Coins,
    Gem,
    Image,
    LockKeyhole,
    Palette,
    Plus,
} from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import ActionHint from '@/components/ActionHint.vue';
import GameAssetArtwork from '@/components/GameAssetArtwork.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import { purchaseShortfall } from '@/lib/purchaseAvailability';
import { purchase, update } from '@/routes/pets/appearance';
import type {
    AppearanceAsset,
    AssetCurrency,
    AssetKind,
    AssetPrice,
    PetAppearance,
} from '@/types/appearance';

const props = defineProps<{ petId: number; appearance: PetAppearance }>();
const { t, number } = useI18n();
const page = usePage();
const id = useId();
const open = ref(false);
const kind = ref<AssetKind>('portrait');
const selectedId = ref<number | null>(null);
const preferredCurrency = ref<AssetCurrency>('coins');
const form = useForm<{
    asset_id: number | null;
    expected_price: number | null;
    expected_currency: AssetCurrency | null;
}>({
    asset_id: null,
    expected_price: null,
    expected_currency: null,
});
const portraits = computed(() =>
    props.appearance.assets.filter(
        (asset) => asset.kind === 'portrait' && asset.unlocked,
    ),
);
const options = computed(() =>
    props.appearance.assets.filter((asset) => asset.kind === kind.value),
);
const selected = computed(() =>
    options.value.find((asset) => asset.id === selectedId.value),
);
const currency = computed({
    get: () =>
        selected.value?.prices.find(
            (price) => price.currency === preferredCurrency.value,
        )?.currency ??
        selected.value?.prices[0]?.currency ??
        null,
    set: (value: AssetCurrency | null) => {
        if (value) preferredCurrency.value = value;
        form.clearErrors();
    },
});
const selectedPrice = computed(() =>
    selected.value?.prices.find((price) => price.currency === currency.value),
);
const appliedId = computed(() =>
    kind.value === 'portrait'
        ? props.appearance.portraitId
        : props.appearance.backgroundId,
);
const shortfall = computed(() =>
    selectedPrice.value
        ? purchaseShortfall(
              selectedPrice.value.amount,
              Number(page.props.auth.user[selectedPrice.value.currency]),
          )
        : 0,
);
const actionReason = computed(() => {
    if (!selected.value) return t('Choose an appearance to continue.');
    if (selected.value.id === appliedId.value)
        return t('This appearance is already in use.');
    if (!selected.value.unlocked && !selectedPrice.value)
        return t('This appearance is currently unavailable.');
    if (!selected.value.unlocked && shortfall.value > 0)
        return t(
            currency.value === 'gems'
                ? 'You need {amount} more gems.'
                : 'You need {amount} more coins.',
            { amount: number(shortfall.value) },
        );
    return null;
});

function showCatalogue(assetKind: AssetKind) {
    kind.value = assetKind;
    selectedId.value =
        assetKind === 'portrait'
            ? props.appearance.portraitId
            : props.appearance.backgroundId;
    form.clearErrors();
    open.value = true;
}

function apply(asset: AppearanceAsset, price?: AssetPrice) {
    if (form.processing || (!asset.unlocked && !price)) return;
    if (
        price &&
        !asset.unlocked &&
        purchaseShortfall(
            price.amount,
            Number(page.props.auth.user[price.currency]),
        ) > 0
    )
        return;
    form.clearErrors();
    form.asset_id = asset.id;
    form.expected_price = price?.amount ?? null;
    form.expected_currency = price?.currency ?? null;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };
    if (asset.unlocked) form.put(update.url(props.petId), options);
    else form.post(purchase.url(props.petId), options);
}

function priceLabel(price: AssetPrice) {
    return t(price.currency === 'gems' ? '{amount} gems' : '{amount} coins', {
        amount: number(price.amount),
    });
}
</script>

<template>
    <details class="pet-gallery pet-appearance-disclosure">
        <summary>
            <Palette :size="17" aria-hidden="true" />
            <span>{{ t('Appearance') }}</span>
            <ChevronDown :size="17" aria-hidden="true" />
        </summary>
        <div class="pet-gallery-strip" :aria-label="t('Dog photos')">
            <button
                v-for="asset in portraits"
                :key="asset.id"
                type="button"
                class="pet-photo-option"
                :class="{ 'is-selected': asset.id === appearance.portraitId }"
                :aria-pressed="asset.id === appearance.portraitId"
                :aria-label="asset.name"
                :title="asset.name"
                :disabled="form.processing"
                @click="apply(asset)"
            >
                <GameAssetArtwork :asset-id="asset.id" lazy />
            </button>
            <Button
                type="button"
                variant="secondary"
                class="pet-add-photo"
                :disabled="form.processing"
                @click="showCatalogue('portrait')"
            >
                <Plus :size="18" />{{ t('Add photo') }}
            </Button>
            <Button
                type="button"
                variant="secondary"
                class="pet-add-photo"
                :disabled="form.processing"
                @click="showCatalogue('background')"
            >
                <Image :size="18" />{{ t('Change background') }}
            </Button>
        </div>
        <InputError v-if="!open" :message="form.errors.asset_id" role="alert" />
        <Dialog v-model:open="open">
            <DialogContent class="appearance-dialog">
                <DialogHeader>
                    <DialogTitle>{{
                        t(
                            kind === 'portrait'
                                ? 'Choose a photo'
                                : 'Choose a background',
                        )
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t(
                            'Unlock once and use with all compatible dogs on your account.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <div v-if="options.length" class="appearance-grid">
                    <button
                        v-for="asset in options"
                        :key="asset.id"
                        type="button"
                        class="appearance-option"
                        :class="{
                            'is-selected': selectedId === asset.id,
                            'is-background': kind === 'background',
                        }"
                        :aria-pressed="selectedId === asset.id"
                        :disabled="form.processing"
                        @click="
                            selectedId = asset.id;
                            form.clearErrors();
                        "
                    >
                        <span class="appearance-preview">
                            <GameAssetArtwork :asset-id="asset.id" lazy />
                            <span
                                v-if="selectedId === asset.id"
                                class="appearance-check"
                                ><Check :size="16"
                            /></span>
                        </span>
                        <span class="appearance-option-name">
                            <GameAssetArtwork
                                v-if="kind === 'portrait'"
                                :asset-id="asset.id"
                                variant="icon"
                                lazy
                            />
                            {{ asset.name }}
                        </span>
                        <span class="appearance-price">
                            <template v-if="asset.id === appliedId"
                                ><Check :size="14" />{{
                                    t('Selected')
                                }}</template
                            >
                            <template v-else-if="asset.unlocked">{{
                                t(
                                    asset.prices.length === 0
                                        ? 'Free'
                                        : 'Unlocked',
                                )
                            }}</template>
                            <template v-else>
                                <LockKeyhole :size="13" />
                                <template
                                    v-for="(price, index) in asset.prices"
                                    :key="price.currency"
                                >
                                    <span v-if="index">{{ t('or') }}</span>
                                    <span class="appearance-price-amount">
                                        <component
                                            :is="
                                                price.currency === 'gems'
                                                    ? Gem
                                                    : Coins
                                            "
                                            :size="14"
                                        />
                                        {{ priceLabel(price) }}
                                    </span>
                                </template>
                            </template>
                        </span>
                    </button>
                </div>
                <p v-else class="field-hint">
                    {{ t('No matching appearances are available yet.') }}
                </p>
                <fieldset
                    v-if="selected && !selected.unlocked"
                    class="appearance-payment"
                    :disabled="form.processing"
                >
                    <legend>{{ t('Pay with') }}</legend>
                    <div class="appearance-payment-options">
                        <label
                            v-for="price in selected.prices"
                            :key="price.currency"
                            class="appearance-payment-option"
                        >
                            <input
                                v-model="currency"
                                type="radio"
                                name="appearance-currency"
                                :value="price.currency"
                            />
                            <component
                                :is="price.currency === 'gems' ? Gem : Coins"
                                :size="18"
                            />
                            {{ priceLabel(price) }}
                        </label>
                    </div>
                </fieldset>
                <InputError
                    :message="
                        form.errors.asset_id ||
                        form.errors.expected_price ||
                        form.errors.expected_currency
                    "
                    role="alert"
                />
                <ActionHint
                    :id="id + '-appearance-reason'"
                    :message="actionReason"
                    :tone="selected?.id === appliedId ? 'info' : 'warning'"
                />
                <DialogFooter class="appearance-footer">
                    <p class="field-hint">
                        {{
                            t(
                                'Appearance does not change your dog’s characteristics.',
                            )
                        }}
                    </p>
                    <Button
                        type="button"
                        :disabled="Boolean(actionReason) || form.processing"
                        :aria-describedby="
                            actionReason ? id + '-appearance-reason' : undefined
                        "
                        :aria-busy="form.processing"
                        @click="selected && apply(selected, selectedPrice)"
                    >
                        <template v-if="form.processing">{{
                            t('Saving…')
                        }}</template>
                        <template
                            v-else-if="
                                selected && !selected.unlocked && selectedPrice
                            "
                            >{{
                                t('Buy for {price} and use', {
                                    price: priceLabel(selectedPrice),
                                })
                            }}</template
                        >
                        <template v-else>{{
                            t('Use this appearance')
                        }}</template>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </details>
</template>
