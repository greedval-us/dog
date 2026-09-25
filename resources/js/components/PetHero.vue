<script setup lang="ts">
import {
    CalendarDays,
    Camera,
    ChevronLeft,
    ChevronRight,
    Ellipsis,
    Heart,
    Mars,
    Pencil,
    Plus,
    ShieldCheck,
    Venus,
} from '@lucide/vue';
import BreedArtwork from '@/components/BreedArtwork.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import type { PlayerPet } from '@/types/pet';

defineProps<{ pet: PlayerPet }>();
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
        <header class="pet-hero-identity">
            <p class="pet-breadcrumb">
                {{ t('My dog') }} <ChevronRight :size="14" />
                <span>{{ pet.name }}</span>
            </p>
            <div class="pet-name-row">
                <h1 :class="{ 'pet-name-long': pet.name.length > 24 }">
                    {{ pet.name }}
                </h1>
                <Button
                    type="button"
                    variant="plain"
                    size="icon"
                    class="pet-glass-button"
                    disabled
                    :aria-label="t('Rename — coming soon')"
                    :title="t('Rename — coming soon')"
                    ><Pencil :size="18"
                /></Button>
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
            <div class="pet-favorite-row">
                <span v-if="pet.isFavorite" class="pet-favorite"
                    ><Heart :size="17" />{{ t('My best friend') }}</span
                >
                <Button
                    v-else
                    variant="plain"
                    type="button"
                    class="pet-glass-button"
                    disabled
                    :title="t('Favorites — coming soon')"
                    ><Heart :size="17" />{{ t('Add to favorites') }}</Button
                >
                <Button
                    variant="plain"
                    size="icon"
                    type="button"
                    class="pet-glass-button"
                    disabled
                    :aria-label="t('More actions — coming soon')"
                    :title="t('More actions — coming soon')"
                    ><Ellipsis
                /></Button>
            </div>
            <p class="pet-hero-quote">
                {{ t('Good dogs make the world a kinder place.') }}
                <Heart :size="20" />
            </p>
        </header>
        <figure class="pet-hero-portrait">
            <BreedArtwork
                :breed="pet.illustration"
                :alt="t('Illustration of {breed}', { breed: pet.breed })"
            />
            <figcaption>{{ t('Breed illustration') }}</figcaption>
        </figure>
        <div class="pet-gallery" :aria-label="t('Dog photos')">
            <div class="pet-gallery-strip">
                <Button
                    type="button"
                    variant="plain"
                    size="icon"
                    class="pet-gallery-arrow"
                    disabled
                    :aria-label="t('Previous photo — coming soon')"
                    :title="t('Photos — coming soon')"
                    ><ChevronLeft
                /></Button>
                <span
                    class="pet-photo-current"
                    :aria-label="t('Breed illustration')"
                    ><BreedArtwork :breed="pet.illustration" variant="icon"
                /></span>
                <span
                    v-for="slot in 2"
                    :key="slot"
                    class="pet-photo-slot"
                    aria-hidden="true"
                    ><Camera :size="21"
                /></span>
                <Button
                    type="button"
                    variant="plain"
                    class="pet-add-photo"
                    disabled
                    :title="t('Photos — coming soon')"
                >
                    <Plus :size="20" /><span
                        >{{ t('Add photo')
                        }}<small>{{ t('Soon') }}</small></span
                    >
                </Button>
                <Button
                    type="button"
                    variant="plain"
                    size="icon"
                    class="pet-gallery-arrow"
                    disabled
                    :aria-label="t('Next photo — coming soon')"
                    :title="t('Photos — coming soon')"
                    ><ChevronRight
                /></Button>
            </div>
        </div>
    </section>
</template>
