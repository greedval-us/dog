<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/useI18n';
import { index as market } from '@/routes/pet-market';
import { destroy, store } from '@/routes/pets/sale';
import type { PetOwnership } from '@/types/pet-market';

defineProps<{ petId: number; ownership: PetOwnership }>();
const { t } = useI18n();
</script>

<template>
    <div class="pet-sale-form">
        <p v-if="ownership.listing" class="field-hint">
            {{
                t(
                    'Your dog is listed for {amount} coins. Care continues while you wait for a buyer.',
                    { amount: ownership.listing.price },
                )
            }}
        </p>
        <Form
            v-bind="store.form(petId)"
            :options="{ preserveScroll: true }"
            v-slot="{ errors, processing }"
            class="form-stack"
        >
            <FormField
                :id="'sale-price-' + petId"
                :label="t('Sale price, coins')"
                :error="errors.price"
                v-slot="{ field }"
            >
                <Input
                    v-bind="field"
                    name="price"
                    type="number"
                    :default-value="ownership.listing?.price ?? ''"
                    min="1"
                    max="1000000"
                    step="1"
                    required
                    :disabled="processing || Boolean(ownership.reason)"
                />
            </FormField>
            <InputError :message="errors.ownership" role="alert" />
            <p v-if="ownership.reason" class="field-hint" role="status">
                {{ ownership.reason }}
            </p>
            <Button
                type="submit"
                :disabled="processing || Boolean(ownership.reason)"
                :aria-busy="processing"
                >{{
                    processing
                        ? t('Saving…')
                        : ownership.listing
                          ? t('Update sale price')
                          : t('List for sale')
                }}</Button
            >
        </Form>
        <Form
            v-if="ownership.listing"
            v-bind="destroy.form(petId)"
            :options="{ preserveScroll: true }"
            v-slot="{ processing, errors }"
        >
            <Button type="submit" variant="outline" :disabled="processing">{{
                t('Withdraw listing')
            }}</Button>
            <InputError :message="errors.ownership" role="alert" />
        </Form>
        <Link :href="market()" class="text-link">{{ t('Dog market') }}</Link>
    </div>
</template>
