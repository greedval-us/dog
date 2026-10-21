<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PetSaleCard from '@/components/PetSaleCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { destroy } from '@/routes/pets/sale';
import type { PetSale } from '@/types/pet-market';

defineProps<{ listing: PetSale }>();
const { t } = useI18n();
</script>

<template>
    <PetSaleCard :listing="listing">
        <Form
            v-bind="destroy.form(listing.pet.id)"
            :options="{ preserveScroll: true }"
            v-slot="{ processing, errors }"
        >
            <Button type="submit" variant="outline" :disabled="processing">{{
                t('Withdraw listing')
            }}</Button>
            <InputError :message="errors.ownership" role="alert" />
        </Form>
    </PetSaleCard>
</template>
