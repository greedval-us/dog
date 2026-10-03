<script setup lang="ts">
import { Deferred, Head, Link, router, usePoll } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import GameAssetArtwork from '@/components/GameAssetArtwork.vue';
import PetAppearanceControls from '@/components/PetAppearanceControls.vue';
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
import type { PetSkills } from '@/types/pet-skill';

defineProps<{
    pet: PlayerPet | null;
    canClaimStarterPet: boolean;
    appearance?: PetAppearance | null;
    slots: PetSlot[];
    care?: PetCare | null;
    skills?: PetSkills | null;
}>();
const { t } = useI18n();

usePoll(60_000, { only: ['pet', 'care', 'skills'] });
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
        <div v-else class="pet-dossier">
            <img
                v-if="appearance?.backgroundId"
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
                <PetHero :pet="pet" :care="care ?? null">
                    <template #appearance>
                        <Deferred data="appearance">
                            <template #fallback>
                                <figure class="pet-hero-portrait">
                                    <div
                                        class="dashboard-loading dashboard-skeleton-portrait"
                                        role="status"
                                    >
                                        <span class="sr-only">{{
                                            t('Loading...')
                                        }}</span>
                                    </div>
                                </figure>
                            </template>
                            <template #rescue="{ reloading }">
                                <figure class="pet-hero-portrait">
                                    <GameAssetArtwork
                                        :asset-id="null"
                                        :alt="
                                            t('Illustration of {breed}', {
                                                breed: pet.breed,
                                            })
                                        "
                                    />
                                    <figcaption role="alert">
                                        <p>
                                            {{
                                                t(
                                                    'Could not load data. Please retry.',
                                                )
                                            }}
                                        </p>
                                        <Button
                                            :disabled="reloading"
                                            @click="
                                                router.reload({
                                                    only: ['appearance'],
                                                })
                                            "
                                            >{{ t('Retry') }}</Button
                                        >
                                    </figcaption>
                                </figure>
                            </template>
                            <figure class="pet-hero-portrait">
                                <Transition name="pet-portrait" mode="out-in">
                                    <GameAssetArtwork
                                        :key="
                                            appearance?.portraitId ??
                                            'placeholder'
                                        "
                                        :asset-id="
                                            appearance?.portraitId ?? null
                                        "
                                        :alt="
                                            t('Illustration of {breed}', {
                                                breed: pet.breed,
                                            })
                                        "
                                    />
                                </Transition>
                            </figure>
                            <PetAppearanceControls
                                v-if="
                                    appearance &&
                                    pet.lifecycle.status === 'active'
                                "
                                :pet-id="pet.id"
                                :appearance="appearance"
                            />
                        </Deferred>
                    </template>
                </PetHero>
                <aside
                    class="pet-sidebar"
                    :aria-label="t('Wellbeing and care')"
                >
                    <PetCondition :states="pet.states" />
                </aside>
            </div>
            <Deferred data="care">
                <template #fallback>
                    <div class="dashboard-loading" role="status">
                        {{ t('Loading...') }}
                    </div>
                </template>
                <template #rescue="{ reloading }">
                    <div role="alert">
                        <p>{{ t('Could not load data. Please retry.') }}</p>
                        <Button
                            :disabled="reloading"
                            @click="router.reload({ only: ['care'] })"
                            >{{ t('Retry') }}</Button
                        >
                    </div>
                </template>
                <PetQuickActions
                    v-if="care"
                    :key="pet.id"
                    class="pet-care-dashboard"
                    :pet="pet"
                    :care="care"
                />
            </Deferred>
            <PetDetails
                :key="pet.id"
                :pet="pet"
                :care="care ?? null"
                :skills="skills ?? null"
            />
        </div>
    </div>
</template>
