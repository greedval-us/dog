<script setup lang="ts">
import {
    CalendarDays,
    Footprints,
    Heart,
    Medal,
    Pencil,
    Quote,
    Stethoscope,
    Trophy,
} from '@lucide/vue';
import DogStats from '@/components/DogStats.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { sizeLabels } from '@/lib/petLabels';
import type { PlayerPet } from '@/types/pet';
import type { PetCare } from '@/types/pet-care';
import { Link } from '@inertiajs/vue3';
import { index as gameEvents } from '@/routes/game-events';
import { index as veterinarian } from '@/routes/veterinarian';

withDefaults(
    defineProps<{ pet: PlayerPet; care: PetCare | null; readOnly?: boolean }>(),
    { readOnly: false },
);
const { t, number } = useI18n();
const traits: Record<string, string> = {
    friendly: 'Friendly',
    active: 'Active',
    loyal: 'Loyal',
    smart: 'Smart',
    fast_learner: 'Quick learner',
};
const events = [
    {
        label: 'Dog shows',
        icon: Trophy,
        action: 'Apply',
        tone: 'amber',
        href: gameEvents({ query: { kind: 'exhibition' } }),
        description: 'Show your dog’s breed type and earn titles.',
    },
    {
        label: 'Competitions',
        icon: Medal,
        action: 'Prepare',
        tone: 'blue',
        href: gameEvents({ query: { kind: 'competition' } }),
        description: 'Choose a discipline and prepare your next start.',
    },
    {
        label: 'Veterinary checkup',
        icon: Stethoscope,
        action: 'Schedule',
        tone: 'rose',
        href: veterinarian(),
        description: 'Treatment and preventive care for your dog.',
    },
] as const;
</script>

<template>
    <div class="pet-overview-grid" :class="{ 'is-read-only': readOnly }">
        <SurfaceCard :title="t('About the dog')" class="pet-about">
            <p class="pet-description">
                {{
                    pet.description ||
                    t(
                        readOnly
                            ? '{name} will always be part of your story.'
                            : '{name} is starting a new story with you.',
                        {
                            name: pet.name,
                        },
                    )
                }}
            </p>
            <dl class="pet-facts">
                <div>
                    <dt>{{ t('Coat color') }}</dt>
                    <dd>{{ pet.coatColor }}</dd>
                </div>
                <div>
                    <dt>{{ t('Size') }}</dt>
                    <dd>{{ t(sizeLabels[pet.size]) }}</dd>
                </div>
                <div>
                    <dt>{{ t('Generation') }}</dt>
                    <dd>{{ number(pet.generation) }}</dd>
                </div>
            </dl>
            <div v-if="pet.traits.length" class="pet-traits">
                <span v-for="trait in pet.traits" :key="trait"
                    ><Heart :size="14" />{{ t(traits[trait] ?? trait) }}</span
                >
            </div>
            <p v-else-if="!readOnly" class="pet-feature-note">
                {{
                    t('Personality traits will appear here as your dog grows.')
                }}
            </p>
            <blockquote class="pet-friendship-quote">
                <Quote :size="25" />
                <p>
                    {{
                        t(
                            'Dogs are not our whole life, but they make our lives whole.',
                        )
                    }}
                </p>
            </blockquote>
            <Button
                v-if="!readOnly"
                type="button"
                variant="plain"
                class="pet-edit-description"
                disabled
                :title="t('Editing the description — coming soon')"
                ><Pencil :size="14" />{{ t('Edit description')
                }}<span class="coming-soon-badge">{{ t('Soon') }}</span></Button
            >
            <div
                v-if="pet.exterior || (readOnly && pet.titles?.length)"
                class="pet-event-credentials"
            >
                <div v-if="pet.exterior" class="event-exterior">
                    <h3>{{ t('Breed conformation') }}</h3>
                    <dl class="event-exterior-values">
                        <div v-for="(value, key) in pet.exterior" :key="key">
                            <dt>{{ t(`events.exterior.${key}`) }}</dt>
                            <dd>{{ number(value) }} / 100</dd>
                        </div>
                    </dl>
                </div>
                <div
                    v-if="readOnly && pet.titles?.length"
                    class="event-exterior"
                >
                    <h3>{{ t('Dog titles') }}</h3>
                    <ul class="event-title-list">
                        <li
                            v-for="(title, titleIndex) in pet.titles"
                            :key="titleIndex"
                        >
                            <Trophy :size="16" aria-hidden="true" /><span
                                >{{ title.name
                                }}<small
                                    >{{
                                        t(
                                            `events.discipline.${title.discipline}`,
                                        )
                                    }}
                                    ·
                                    {{
                                        t(`events.frequency.${title.frequency}`)
                                    }}</small
                                ></span
                            >
                        </li>
                    </ul>
                </div>
            </div>
        </SurfaceCard>
        <SurfaceCard
            :title="t('Main attributes')"
            :description="t('Current value / genetic potential.')"
            class="pet-attributes"
        >
            <DogStats :values="pet.stats" variant="bars" />
        </SurfaceCard>
        <SurfaceCard v-if="!readOnly" class="pet-schedule">
            <template #header
                ><div class="pet-section-heading">
                    <h2>{{ t('Current activity') }}</h2>
                </div></template
            >
            <div class="pet-activity-placeholder">
                <span><Footprints :size="30" /></span>
                <div>
                    <strong>{{
                        t(
                            care?.active?.label ??
                                (care?.busy
                                    ? 'Your dog is busy with another activity.'
                                    : 'A little adventure ahead'),
                        )
                    }}</strong>
                    <p>
                        {{ t('Choose an action to compare its options.') }}
                    </p>
                    <Button as-child variant="link"
                        ><a href="#pet-care">{{
                            t('Quick actions')
                        }}</a></Button
                    >
                </div>
            </div>
            <div class="pet-section-heading pet-events-heading">
                <h2>{{ t('Upcoming events') }}</h2>
            </div>
            <ul class="pet-events">
                <li
                    v-for="event in events"
                    :key="event.label"
                    :class="'pet-tone-' + event.tone"
                >
                    <component :is="event.icon" :size="20" aria-hidden="true" />
                    <span
                        >{{ t(event.label)
                        }}<small>{{ t(event.description) }}</small></span
                    >
                    <Button as-child variant="secondary" size="sm"
                        ><Link :href="event.href"
                            ><CalendarDays
                                v-if="event.action === 'Schedule'"
                                :size="14"
                            />{{ t(event.action) }}</Link
                        ></Button
                    >
                </li>
            </ul>
        </SurfaceCard>
    </div>
</template>
