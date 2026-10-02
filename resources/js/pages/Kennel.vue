<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    ChevronDown,
    Coins,
    Gift,
    House,
    PawPrint,
    Shuffle,
} from '@lucide/vue';
import { computed, useId, useTemplateRef } from 'vue';
import ActionHint from '@/components/ActionHint.vue';
import BreedArtwork from '@/components/BreedArtwork.vue';
import DogStats from '@/components/DogStats.vue';
import FormField from '@/components/FormField.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/useI18n';
import { sizeLabels } from '@/lib/petLabels';
import { purchaseShortfall } from '@/lib/purchaseAvailability';
import { dashboard, petScene } from '@/routes';
import { purchase, store } from '@/routes/kennel';
import { show as player } from '@/routes/players';
import type { StarterBreed } from '@/types/pet';

const props = defineProps<{
    breeds: StarterBreed[];
    canClaimStarterPet: boolean;
    freeSlots: number;
    price: number;
    adoptionToken: string;
}>();
const { t, number } = useI18n();
const page = usePage();
const id = useId();
const form = useForm({
    dog_id: props.breeds[0]?.id ?? (null as number | null),
    name: '',
    adoption: '',
    expected_price: props.price,
    adoption_token: props.adoptionToken,
});
const selectedBreed = computed(() =>
    props.breeds.find((breed) => breed.id === form.dog_id),
);
const affordable = computed(
    () =>
        props.canClaimStarterPet ||
        Number(page.props.auth.user.coins) >= props.price,
);
const actionReason = computed(() => {
    if (props.freeSlots < 1)
        return t(
            'You need a free dog slot. Unlock a place on the My dog page.',
        );
    if (!affordable.value)
        return t('You need {amount} more coins.', {
            amount: number(
                purchaseShortfall(
                    props.price,
                    Number(page.props.auth.user.coins),
                ),
            ),
        });
    if (!selectedBreed.value) return t('Choose a breed to continue.');
    return null;
});
const adoptionForm = useTemplateRef<HTMLFormElement>('adoptionForm');
function submit() {
    if (form.processing || actionReason.value) return;
    form.transform(({ dog_id, name, expected_price, adoption_token }) =>
        props.canClaimStarterPet
            ? { dog_id, name }
            : { dog_id, name, expected_price, adoption_token },
    ).submit(props.canClaimStarterPet ? store() : purchase(), {
        preserveScroll: true,
        onError: () =>
            adoptionForm.value
                ?.querySelector<HTMLElement>(
                    '[aria-invalid="true"], [role="alert"]',
                )
                ?.focus(),
    });
}
</script>

<template>
    <div class="kennel-page">
        <Head :title="t('Kennel')" />
        <div class="kennel-heading">
            <Heading
                :title="t('Kennel')"
                :description="t('Your friendship starts here.')"
            />
            <span class="kennel-gift">
                <Gift v-if="canClaimStarterPet" :size="21" aria-hidden="true" />
                <Coins v-else :size="21" aria-hidden="true" />
                {{
                    canClaimStarterPet
                        ? t('Your first dog is free')
                        : t('A new friend for {amount} coins', {
                              amount: number(price),
                          })
                }}
            </span>
        </div>
        <div class="kennel-banner">
            <img :src="petScene.url()" alt="" aria-hidden="true" />
            <p>
                {{ t('New stories start here') }}
                <PawPrint :size="20" aria-hidden="true" />
            </p>
            <span>{{ t('Friends for life') }}</span>
        </div>
        <SurfaceCard
            v-if="breeds.length === 0"
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
            <fieldset
                class="breed-picker"
                :disabled="form.processing"
                :aria-describedby="
                    form.errors.dog_id ? 'breed-error' : undefined
                "
            >
                <legend class="sr-only">{{ t('Choose a breed') }}</legend>
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
                        <span class="breed-choice-scene">
                            <img
                                class="breed-choice-landscape"
                                :src="petScene.url()"
                                alt=""
                                aria-hidden="true"
                            />
                            <BreedArtwork :breed="breed.illustration" />
                        </span>
                        <span class="breed-choice-check" aria-hidden="true"
                            ><Check v-if="form.dog_id === breed.id" :size="19"
                        /></span>
                        <span class="breed-choice-name">{{ breed.name }}</span>
                        <span class="breed-choice-size">{{
                            t(sizeLabels[breed.size])
                        }}</span>
                    </label>
                </div>
                <InputError id="breed-error" :message="form.errors.dog_id" />
            </fieldset>
            <SurfaceCard v-if="selectedBreed" class="kennel-adoption">
                <div class="kennel-adoption-grid">
                    <div class="kennel-name-panel form-stack">
                        <h2>{{ t('Meet your new friend') }}</h2>
                        <FormField
                            id="pet-name"
                            :label="t('Dog name')"
                            :error="form.errors.name"
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
                            <Shuffle :size="18" aria-hidden="true" />{{
                                t(
                                    'Sex and coat color are assigned randomly when you take your dog home.',
                                )
                            }}
                        </p>
                    </div>
                    <div class="kennel-checkout form-stack">
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
                        <span class="kennel-availability"
                            ><House :size="17" aria-hidden="true" />{{
                                t('Free places: {count}', {
                                    count: number(freeSlots),
                                })
                            }}</span
                        >
                        <ActionHint
                            :id="id + '-adoption-reason'"
                            :message="actionReason"
                        >
                            <Link
                                v-if="freeSlots > 0 && !affordable"
                                :href="player(page.props.auth.user.username)"
                                class="text-link"
                                >{{ t('Earn coins at daily work') }}</Link
                            >
                        </ActionHint>
                        <InputError
                            :message="
                                form.errors.adoption ||
                                form.errors.expected_price ||
                                form.errors.adoption_token
                            "
                            role="alert"
                            tabindex="-1"
                        />
                        <Button v-if="freeSlots < 1" as-child
                            ><Link :href="dashboard()"
                                >{{ t('Manage dog places')
                                }}<ArrowRight /></Link
                        ></Button>
                        <Button
                            v-else
                            type="submit"
                            :disabled="form.processing || Boolean(actionReason)"
                            :aria-describedby="
                                actionReason
                                    ? id + '-adoption-reason'
                                    : undefined
                            "
                            :aria-busy="form.processing"
                            data-test="adopt-kennel-pet"
                        >
                            {{
                                form.processing
                                    ? t('Bringing your dog home...')
                                    : canClaimStarterPet
                                      ? t('Take home for free')
                                      : t('Take home for {amount} coins', {
                                            amount: number(price),
                                        })
                            }}
                        </Button>
                        <p class="kennel-price-note">
                            {{
                                canClaimStarterPet
                                    ? t(
                                          'Your first dog is free. Each next dog costs {amount} coins.',
                                          { amount: number(price) },
                                      )
                                    : t('One dog takes one free place.')
                            }}
                        </p>
                    </div>
                </div>
            </SurfaceCard>
            <details v-if="selectedBreed" class="kennel-potential">
                <summary>
                    {{ t('Breed potential')
                    }}<ChevronDown :size="17" aria-hidden="true" />
                </summary>
                <p>{{ selectedBreed.description }}</p>
                <DogStats :values="selectedBreed.potentials" />
                <p class="field-hint">
                    {{
                        t(
                            'Starting genetic limits. Trained skills begin at zero.',
                        )
                    }}
                    {{
                        t(
                            'Breed illustrations are examples. Your dog’s coat color may differ.',
                        )
                    }}
                </p>
            </details>
        </form>
    </div>
</template>
