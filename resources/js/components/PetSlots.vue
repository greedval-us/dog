<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Check, Coins, Gem, LockKeyhole, PawPrint, Plus } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import ActionHint from '@/components/ActionHint.vue';
import InputError from '@/components/InputError.vue';
import GameAssetArtwork from '@/components/GameAssetArtwork.vue';
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
import { dashboard } from '@/routes';
import { index as kennel } from '@/routes/kennel';
import { store } from '@/routes/pet-slots';
import type { PetSlot } from '@/types/pet-slot';

const props = defineProps<{
    slots: PetSlot[];
    selectedPetId: number | null;
    selectedPortraitId: number | null;
    canClaimStarterPet: boolean;
}>();
const { t, number } = useI18n();
const id = useId();
const page = usePage();
const open = ref(false);
const selected = ref<PetSlot | null>(null);
const form = useForm({
    slot: 2,
    currency: 'coins' as 'coins' | 'gems',
    expected_price: 100,
});
const unlocked = computed(
    () => props.slots.filter((slot) => slot.unlocked).length,
);
const price = computed(() => selected.value?.[form.currency] ?? 0);
const shortfall = computed(() =>
    purchaseShortfall(price.value, Number(page.props.auth.user[form.currency])),
);
const affordable = computed(() => shortfall.value === 0);
const fundsMessage = computed(() =>
    shortfall.value > 0
        ? t(
              form.currency === 'gems'
                  ? 'You need {amount} more gems.'
                  : 'You need {amount} more coins.',
              { amount: number(shortfall.value) },
          )
        : null,
);

function choose(slot: PetSlot) {
    selected.value = slot;
    form.slot = slot.number;
    form.currency = 'coins';
    form.clearErrors();
    open.value = true;
}

function purchase() {
    if (!selected.value?.purchasable || !affordable.value || form.processing)
        return;
    form.expected_price = price.value;
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <section class="pet-slots" aria-labelledby="pet-slots-title">
        <header class="pet-slots-header">
            <div class="pet-slots-heading">
                <span class="pet-slots-emblem"
                    ><PawPrint :size="16" aria-hidden="true"
                /></span>
                <h2 id="pet-slots-title">{{ t('My dogs') }}</h2>
            </div>
            <span
                class="pet-slots-count"
                :aria-label="
                    t('Dog slots: {count} of 9 unlocked', { count: unlocked })
                "
            >
                <span>{{ t('Places') }}</span
                ><strong>{{ unlocked }}</strong
                ><span>/ 9</span>
            </span>
        </header>
        <div
            class="pet-slots-grid"
            tabindex="0"
            role="group"
            aria-labelledby="pet-slots-title"
        >
            <div
                v-for="slot in slots"
                :key="slot.number"
                class="pet-slot"
                :class="{
                    'is-selected': slot.pet?.id === selectedPetId,
                    'is-locked': !slot.unlocked,
                    'is-next': slot.purchasable,
                }"
            >
                <Link
                    v-if="slot.pet"
                    :href="dashboard({ query: { pet: slot.pet.id } })"
                    class="pet-slot-action"
                    :title="slot.pet.name"
                    :aria-label="slot.pet.name"
                    :aria-current="
                        slot.pet.id === selectedPetId ? 'page' : undefined
                    "
                >
                    <span class="pet-slot-avatar">
                        <GameAssetArtwork
                            v-if="
                                slot.pet.id === selectedPetId &&
                                selectedPortraitId
                            "
                            :asset-id="selectedPortraitId"
                            variant="icon"
                        />
                        <PawPrint v-else :size="23" aria-hidden="true" />
                    </span>
                    <span
                        v-if="slot.pet.id === selectedPetId"
                        class="pet-slot-selected"
                        :title="t('Selected dog')"
                        ><Check :size="10" aria-hidden="true"
                    /></span>
                    <span class="pet-slot-name">{{ slot.pet.name }}</span>
                </Link>
                <template v-else-if="slot.unlocked">
                    <Link
                        :href="kennel()"
                        class="pet-slot-action"
                        :title="
                            canClaimStarterPet
                                ? t('Get your first dog')
                                : t('Visit the kennel')
                        "
                        :aria-label="
                            canClaimStarterPet
                                ? t('Get your first dog')
                                : t('Visit the kennel')
                        "
                    >
                        <span class="pet-slot-symbol"
                            ><Plus :size="20" aria-hidden="true" /></span
                        ><span>{{
                            t('Slot {number}', { number: slot.number })
                        }}</span>
                    </Link>
                </template>
                <button
                    v-else
                    type="button"
                    class="pet-slot-action"
                    :disabled="!slot.purchasable || form.processing"
                    :title="
                        t(
                            slot.purchasable
                                ? 'Unlock slot'
                                : 'Open the previous slot first',
                        )
                    "
                    :aria-label="
                        slot.purchasable
                            ? t('Unlock slot {number}', { number: slot.number })
                            : t(
                                  'Locked slot {number}. Open the previous slot first.',
                                  { number: slot.number },
                              )
                    "
                    @click="choose(slot)"
                >
                    <span class="pet-slot-symbol">
                        <Plus
                            v-if="slot.purchasable"
                            :size="20"
                            aria-hidden="true"
                        />
                        <LockKeyhole v-else :size="15" aria-hidden="true" />
                    </span>
                    <span>{{
                        slot.purchasable
                            ? t('Unlock')
                            : t('Slot {number}', { number: slot.number })
                    }}</span>
                </button>
            </div>
        </div>
        <p
            v-if="slots.some((slot) => !slot.unlocked && !slot.purchasable)"
            class="pet-slots-hint"
        >
            <LockKeyhole :size="12" aria-hidden="true" />{{
                t('Open the previous slot first')
            }}
        </p>
        <Dialog v-model:open="open">
            <DialogContent class="pet-slot-dialog">
                <span class="pet-slot-dialog-emblem"
                    ><PawPrint :size="26" aria-hidden="true" /><Plus
                        :size="13"
                        aria-hidden="true"
                /></span>
                <DialogHeader>
                    <DialogTitle>{{
                        t('Unlock slot {number}', {
                            number: selected?.number ?? 2,
                        })
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t(
                            'Buy a permanent place for one more dog. A dog is not included.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <form class="pet-slot-form" @submit.prevent="purchase">
                    <fieldset
                        class="appearance-payment"
                        :disabled="form.processing"
                    >
                        <legend>{{ t('Payment currency') }}</legend>
                        <div class="appearance-payment-options">
                            <label class="appearance-payment-option">
                                <input
                                    v-model="form.currency"
                                    type="radio"
                                    value="coins"
                                    name="slot-currency"
                                    @change="form.clearErrors()"
                                />
                                <Coins :size="18" aria-hidden="true" />{{
                                    t('{amount} coins', {
                                        amount: selected?.coins ?? 0,
                                    })
                                }}
                            </label>
                            <label class="appearance-payment-option">
                                <input
                                    v-model="form.currency"
                                    type="radio"
                                    value="gems"
                                    name="slot-currency"
                                    @change="form.clearErrors()"
                                />
                                <Gem :size="18" aria-hidden="true" />{{
                                    t('{amount} gems', {
                                        amount: selected?.gems ?? 0,
                                    })
                                }}
                            </label>
                        </div>
                    </fieldset>
                    <ActionHint
                        :id="id + '-slot-reason'"
                        :message="fundsMessage"
                    />
                    <InputError
                        :message="
                            form.errors.slot ||
                            form.errors.currency ||
                            form.errors.expected_price
                        "
                        role="alert"
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            :disabled="form.processing"
                            @click="open = false"
                            >{{ t('Cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="form.processing || !affordable"
                            :aria-describedby="
                                fundsMessage ? id + '-slot-reason' : undefined
                            "
                            :aria-busy="form.processing"
                            >{{
                                t(
                                    form.processing
                                        ? 'Unlocking slot...'
                                        : 'Confirm slot purchase',
                                )
                            }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>
