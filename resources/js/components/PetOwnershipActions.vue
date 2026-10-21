<script setup lang="ts">
import PetSaleForm from '@/components/PetSaleForm.vue';
import PetSurrenderDialog from '@/components/PetSurrenderDialog.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { useI18n } from '@/composables/useI18n';
import type { PlayerPet } from '@/types/pet';
import type { PetOwnership } from '@/types/pet-market';

defineProps<{ pet: PlayerPet; ownership: PetOwnership }>();
const { t } = useI18n();
</script>

<template>
    <SurfaceCard
        class="pet-ownership"
        :title="t('Your dog’s home')"
        :description="
            t(
                'Keep caring for your companion, find a new owner or choose a shelter. Retirement does not free a slot.',
            )
        "
    >
        <div class="pet-ownership-options">
            <PetSaleForm
                :key="pet.id + '-' + ownership.listing?.price"
                :pet-id="pet.id"
                :ownership="ownership"
            />
            <div class="pet-ownership-shelter">
                <p>
                    {{ t('Shelter transfer frees a slot without a payment.') }}
                </p>
                <PetSurrenderDialog
                    :pet-id="pet.id"
                    :name="pet.name"
                    :reason="ownership.reason"
                />
            </div>
        </div>
    </SurfaceCard>
</template>
