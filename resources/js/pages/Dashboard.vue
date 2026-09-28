<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PetCondition from '@/components/PetCondition.vue';
import PetDetails from '@/components/PetDetails.vue';
import PetHero from '@/components/PetHero.vue';
import PetQuickActions from '@/components/PetQuickActions.vue';
import PetSlots from '@/components/PetSlots.vue';
import type { PetSlot } from '@/types/pet-slot';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { image } from '@/routes/assets';
import type { PetAppearance } from '@/types/appearance';
import { index as kennel } from '@/routes/kennel';
import type { PlayerPet } from '@/types/pet';
import type { PetCare } from '@/types/pet-care';

defineProps<{
    pet: PlayerPet | null;
    canClaimStarterPet: boolean;
    appearance: PetAppearance | null;
    slots: PetSlot[];
    care: PetCare | null;
}>();
const { t } = useI18n();

usePoll(60_000, { only: ['pet', 'care'] });
</script>

<template>
    <div class="my-dog-page">
        <Head :title="t('My dog')" />
        <PetSlots
            :slots="slots"
            :selected-pet-id="pet?.id ?? null"
            :selected-portrait-id="appearance?.portraitId ?? null"
            :can-claim-starter-pet="canClaimStarterPet"
        />
        <template v-if="!pet">
            <Heading
                :title="t('My dog')"
                :description="t('A place for your future best friend.')"
            />
            <EmptyState
                :title="t('You do not have a dog yet')"
                :description="
                    canClaimStarterPet
                        ? t(
                              'Your first friend is waiting at the kennel. Choose a breed and take your dog home for free.',
                          )
                        : t('There are currently no dogs in your care.')
                "
            >
                <Button as-child
                    ><Link :href="kennel()"
                        >{{ t('Visit the kennel') }} <ArrowRight /></Link
                ></Button>
            </EmptyState>
        </template>
        <div v-else-if="appearance" class="pet-dossier">
            <img
                v-if="appearance.backgroundId"
                class="pet-profile-scene"
                :src="
                    image.url({
                        asset: appearance.backgroundId,
                        variant: 'image',
                    })
                "
                alt=""
                aria-hidden="true"
                width="1672"
                height="941"
            />
            <div class="pet-stage">
                <PetHero :pet="pet" :appearance="appearance" :care="care" />
                <aside
                    class="pet-sidebar"
                    :aria-label="t('Wellbeing and care')"
                >
                    <PetCondition :states="pet.states" />
                    <PetQuickActions
                        v-if="care"
                        :key="pet.id"
                        :pet="pet"
                        :care="care"
                    />
                </aside>
            </div>
            <PetDetails :key="pet.id" :pet="pet" :care="care" />
        </div>
    </div>
</template>
