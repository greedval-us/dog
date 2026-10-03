<script setup lang="ts">
import {
    CalendarDays,
    ChevronRight,
    Flower2,
    Leaf,
    Mars,
    ShieldCheck,
    Venus,
} from '@lucide/vue';
import GameAssetArtwork from '@/components/GameAssetArtwork.vue';
import PetAppearanceControls from '@/components/PetAppearanceControls.vue';
import PetRetirement from '@/components/PetRetirement.vue';
import type { PetAppearance } from '@/types/appearance';
import { useI18n } from '@/composables/useI18n';
import type { PlayerPet } from '@/types/pet';
import StatusEffects from '@/components/StatusEffects.vue';
import type { PetCare } from '@/types/pet-care';

withDefaults(
    defineProps<{
        pet: PlayerPet;
        appearance?: PetAppearance | null;
        care?: PetCare | null;
        readOnly?: boolean;
    }>(),
    { readOnly: false },
);
const { t, locale } = useI18n();
const date = (value: string) =>
    new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(value));
</script>

<template>
    <section class="pet-hero" :aria-label="t('Pet profile')">
        <div class="pet-hero-topline">
            <p class="pet-breadcrumb">
                {{ t(readOnly ? 'Pet memorial hall' : 'My dog') }}
                <ChevronRight :size="14" />
                <span>{{ pet.name }}</span>
            </p>
            <StatusEffects
                v-if="care"
                :key="pet.id"
                compact
                :effects="[...care.buffs, ...care.debuffs]"
                :server-now="care.serverNow"
            />
        </div>
        <header class="pet-hero-identity">
            <div class="pet-name-row">
                <h1 :class="{ 'pet-name-long': pet.name.length > 24 }">
                    {{ pet.name }}
                </h1>
            </div>
            <p class="pet-hero-breed">
                <component
                    :is="pet.sex === 'male' ? Mars : Venus"
                    :size="23"
                /><strong>{{ pet.breed }}</strong>
            </p>
            <div class="pet-tags">
                <span
                    ><CalendarDays :size="15" />{{
                        t('Born {date}', { date: date(pet.bornAt) })
                    }}</span
                >
                <span>{{ t(pet.sex === 'male' ? 'Male' : 'Female') }}</span>
                <span v-if="pet.isPurebred"
                    ><ShieldCheck :size="15" />{{ t('Purebred') }}</span
                >
                <span v-else>{{ t('Mixed breed') }}</span>
            </div>
            <span
                v-if="pet.lifecycle.status !== 'active'"
                class="pet-lifecycle-status"
                :class="'is-' + pet.lifecycle.status"
            >
                <component
                    :is="pet.lifecycle.status === 'retired' ? Leaf : Flower2"
                    :size="16"
                    aria-hidden="true"
                />
                {{
                    t(
                        pet.lifecycle.status === 'retired'
                            ? 'Retired'
                            : 'In loving memory',
                    )
                }}
            </span>
            <PetRetirement
                v-if="
                    !readOnly &&
                    pet.lifecycle.status === 'active' &&
                    pet.lifecycle.canRetire
                "
                :pet="pet"
            />
        </header>
        <slot name="appearance">
            <figure class="pet-hero-portrait">
                <Transition name="pet-portrait" mode="out-in">
                    <GameAssetArtwork
                        :key="appearance?.portraitId ?? 'placeholder'"
                        :asset-id="appearance?.portraitId ?? null"
                        :alt="
                            t('Illustration of {breed}', { breed: pet.breed })
                        "
                    />
                </Transition>
            </figure>
            <PetAppearanceControls
                v-if="
                    appearance && !readOnly && pet.lifecycle.status === 'active'
                "
                :pet-id="pet.id"
                :appearance="appearance"
            />
        </slot>
    </section>
</template>
