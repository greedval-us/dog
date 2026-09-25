<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PetCondition from '@/components/PetCondition.vue';
import PetDetails from '@/components/PetDetails.vue';
import PetHero from '@/components/PetHero.vue';
import PetQuickActions from '@/components/PetQuickActions.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { petScene } from '@/routes';
import { index as kennel } from '@/routes/kennel';
import type { PlayerPet } from '@/types/pet';

defineProps<{ pet: PlayerPet | null; canClaimStarterPet: boolean }>();
const { t } = useI18n();
</script>

<template>
    <div class="my-dog-page">
        <Head :title="t('My dog')" />
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
                <Button v-if="canClaimStarterPet" as-child
                    ><Link :href="kennel()"
                        >{{ t('Visit the kennel') }} <ArrowRight /></Link
                ></Button>
            </EmptyState>
        </template>
        <div v-else class="pet-dossier">
            <img
                class="pet-profile-scene"
                :src="petScene.url()"
                alt=""
                aria-hidden="true"
                width="1672"
                height="941"
            />
            <div class="pet-stage">
                <PetHero :pet="pet" />
                <aside
                    class="pet-sidebar"
                    :aria-label="t('Wellbeing and care')"
                >
                    <PetCondition :states="pet.states" />
                    <PetQuickActions />
                </aside>
            </div>
            <PetDetails :key="pet.id" :pet="pet" />
            <p class="pet-illustration-note">
                {{
                    t(
                        'Breed illustrations are examples. Your dog’s coat color may differ.',
                    )
                }}
            </p>
        </div>
    </div>
</template>
