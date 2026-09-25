<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Check, Gift, Shuffle } from '@lucide/vue';
import { computed, useTemplateRef } from 'vue';
import BreedArtwork from '@/components/BreedArtwork.vue';
import DogStats from '@/components/DogStats.vue';
import EmptyState from '@/components/EmptyState.vue';
import FormActions from '@/components/FormActions.vue';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/useI18n';
import { sizeLabels } from '@/lib/petLabels';
import { dashboard } from '@/routes';
import { store } from '@/routes/kennel';
import type { StarterBreed } from '@/types/pet';

const props = defineProps<{
    breeds: StarterBreed[];
    canClaimStarterPet: boolean;
}>();
const { t } = useI18n();
const form = useForm<{ dog_id: number | null; name: string; adoption: string }>(
    {
        dog_id: props.breeds[0]?.id ?? null,
        name: '',
        adoption: '',
    },
);
const selectedBreed = computed(() =>
    props.breeds.find((breed) => breed.id === form.dog_id),
);
const adoptionForm = useTemplateRef<HTMLFormElement>('adoptionForm');
const submit = () =>
    form
        .transform(({ dog_id, name }) => ({ dog_id, name }))
        .submit(store(), {
            preserveScroll: true,
            onError: () =>
                adoptionForm.value
                    ?.querySelector<HTMLElement>('[aria-invalid="true"]')
                    ?.focus(),
        });
</script>

<template>
    <div class="kennel-page">
        <Head :title="t('Kennel')" />
        <Heading
            :title="t('Kennel')"
            :description="t('Your friendship starts here.')"
        />
        <EmptyState
            v-if="!canClaimStarterPet"
            :title="t('You have already started your story')"
            :description="
                t('The kennel gives one free first dog to each player.')
            "
        >
            <Button as-child
                ><Link :href="dashboard()"
                    >{{ t('Go to my dog') }} <ArrowRight /></Link
            ></Button>
        </EmptyState>
        <SurfaceCard
            v-else-if="breeds.length === 0"
            :title="t('No breeds available yet')"
            :description="t('Please visit the kennel again later.')"
        />
        <form
            v-else
            ref="adoptionForm"
            class="form-stack kennel-form"
            @submit.prevent="submit"
            :aria-busy="form.processing"
        >
            <div class="kennel-intro">
                <span class="kennel-gift"
                    ><Gift :size="18" /> {{ t('Your first dog is free') }}</span
                >
                <p>
                    {{
                        t(
                            'Choose a breed and a name. Sex and coat color will be a surprise.',
                        )
                    }}
                </p>
            </div>
            <fieldset
                class="breed-picker"
                :disabled="form.processing"
                :aria-describedby="
                    form.errors.dog_id ? 'breed-error' : undefined
                "
            >
                <legend>{{ t('Choose a breed') }}</legend>
                <div class="breed-grid">
                    <label
                        v-for="breed in breeds"
                        :key="breed.id"
                        class="breed-choice"
                        :class="{ 'is-selected': form.dog_id === breed.id }"
                    >
                        <input
                            v-model="form.dog_id"
                            type="radio"
                            name="dog_id"
                            :value="breed.id"
                            required
                            :aria-invalid="Boolean(form.errors.dog_id)"
                            :aria-label="breed.name"
                        />
                        <span class="breed-choice-check" aria-hidden="true"
                            ><Check v-if="form.dog_id === breed.id" :size="16"
                        /></span>
                        <BreedArtwork :breed="breed.illustration" />
                        <span class="breed-choice-name">{{ breed.name }}</span>
                        <span class="breed-choice-size">{{
                            t(sizeLabels[breed.size])
                        }}</span>
                        <span class="breed-choice-description">{{
                            breed.description
                        }}</span>
                    </label>
                </div>
                <InputError id="breed-error" :message="form.errors.dog_id" />
            </fieldset>
            <div v-if="selectedBreed" class="kennel-details">
                <SurfaceCard
                    :title="t('Meet your new friend')"
                    :description="
                        t('A name is the beginning of your story together.')
                    "
                >
                    <div class="form-stack">
                        <div class="breed-summary">
                            <BreedArtwork
                                :breed="selectedBreed.illustration"
                                variant="icon"
                            />
                            <div>
                                <strong>{{ selectedBreed.name }}</strong
                                ><span>{{ t('Generation one') }}</span>
                            </div>
                        </div>
                        <FormField
                            id="pet-name"
                            :label="t('Dog name')"
                            :error="form.errors.name"
                            :hint="t('Up to 64 characters.')"
                            v-slot="{ field }"
                        >
                            <Input
                                v-bind="field"
                                v-model="form.name"
                                name="name"
                                required
                                maxlength="64"
                                autocomplete="off"
                                :disabled="form.processing"
                                :placeholder="t('What will you call your dog?')"
                            />
                        </FormField>
                        <p class="kennel-random-note">
                            <Shuffle :size="18" />
                            {{
                                t(
                                    'Sex and coat color are assigned randomly when you take your dog home.',
                                )
                            }}
                        </p>
                        <InputError
                            :message="form.errors.adoption"
                            role="alert"
                        />
                        <FormActions
                            :processing="form.processing"
                            :label="t('Take home for free')"
                            test-id="adopt-starter-pet"
                        />
                        <p class="field-hint">
                            {{
                                t(
                                    'One first dog per account. No coins or gems required.',
                                )
                            }}
                        </p>
                    </div>
                </SurfaceCard>
                <SurfaceCard
                    :title="t('Breed potential')"
                    :description="
                        t(
                            'Starting genetic limits. Trained skills begin at zero.',
                        )
                    "
                >
                    <DogStats :values="selectedBreed.potentials" />
                    <p class="field-hint breed-art-caption">
                        {{
                            t(
                                'Breed illustrations are examples. Your dog’s coat color may differ.',
                            )
                        }}
                    </p>
                </SurfaceCard>
            </div>
        </form>
    </div>
</template>
