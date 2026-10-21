<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, shallowRef } from 'vue';
import ActionHint from '@/components/ActionHint.vue';
import InputError from '@/components/InputError.vue';
import PetSaleCard from '@/components/PetSaleCard.vue';
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
import { index as market, purchase } from '@/routes/pet-market';
import type { PetSale } from '@/types/pet-market';

const props = defineProps<{
    listing: PetSale;
    freeSlots: number;
    purchaseToken: string;
}>();
const page = usePage();
const { t, number } = useI18n();
const open = shallowRef(false);
const form = useForm({
    expected_price: props.listing.price,
    purchase_token: props.purchaseToken,
    ownership: '',
});
const reason = computed(() => {
    if (props.freeSlots < 1)
        return t(
            'You need a free dog slot. Unlock a place on the My dog page.',
        );
    const shortfall = purchaseShortfall(
        props.listing.price,
        Number(page.props.auth.user.coins),
    );
    return shortfall > 0
        ? t('You need {amount} more coins.', { amount: number(shortfall) })
        : null;
});
const error = computed(() => Object.values(form.errors).join(' '));
function choose(): void {
    if (form.processing || reason.value) return;
    form.clearErrors();
    form.expected_price = props.listing.price;
    form.purchase_token = props.purchaseToken;
    open.value = true;
}
function buy(): void {
    if (form.processing || reason.value || !open.value) return;
    form.transform(({ expected_price, purchase_token }) => ({
        expected_price,
        purchase_token,
    })).submit(purchase(props.listing.id), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <PetSaleCard :listing="listing">
        <ActionHint :message="reason" />
        <Button
            type="button"
            :disabled="Boolean(reason) || form.processing"
            @click="choose"
            >{{ t('Buy this dog') }}</Button
        >
        <Dialog v-model:open="open">
            <DialogContent
                :show-close-button="!form.processing"
                @escape-key-down="form.processing && $event.preventDefault()"
                @interact-outside="form.processing && $event.preventDefault()"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        t('Take {name} home?', { name: listing.pet.name })
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t(
                            'Pay {amount} coins to the current owner. Your dog keeps its age, pedigree, skills and awards and takes one slot.',
                            { amount: number(form.expected_price) },
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <p v-if="listing.pet.retired" class="field-hint">
                    {{
                        t(
                            'This dog is retired. Care and veterinary help remain available; its career has ended.',
                        )
                    }}
                </p>
                <InputError :message="error" role="alert" />
                <Button v-if="error" as-child variant="outline"
                    ><Link :href="market()">{{
                        t('Refresh market')
                    }}</Link></Button
                >
                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="form.processing"
                        @click="open = false"
                        >{{ t('Cancel') }}</Button
                    >
                    <Button
                        type="button"
                        :disabled="form.processing || Boolean(reason)"
                        :aria-busy="form.processing"
                        @click="buy"
                        >{{
                            form.processing
                                ? t('Saving…')
                                : t('Buy for {amount} coins', {
                                      amount: number(form.expected_price),
                                  })
                        }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </PetSaleCard>
</template>
