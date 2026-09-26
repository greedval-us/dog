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

defineProps<{ pet: PlayerPet }>();
const { t, number } = useI18n();
const traits: Record<string, string> = {
    friendly: 'Friendly',
    active: 'Active',
    loyal: 'Loyal',
    smart: 'Smart',
    fast_learner: 'Quick learner',
};
const events = [
    { label: 'Dog shows', icon: Trophy, action: 'Apply', tone: 'amber' },
    { label: 'Competitions', icon: Medal, action: 'Prepare', tone: 'blue' },
    {
        label: 'Veterinary checkup',
        icon: Stethoscope,
        action: 'Schedule',
        tone: 'rose',
    },
] as const;
</script>

<template>
    <div class="pet-overview-grid">
        <SurfaceCard :title="t('About the dog')" class="pet-about">
            <p class="pet-description">
                {{
                    pet.description ||
                    t('{name} is starting a new story with you.', {
                        name: pet.name,
                    })
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
            <p v-else class="pet-feature-note">
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
                type="button"
                variant="plain"
                class="pet-edit-description"
                disabled
                :title="t('Editing the description — coming soon')"
                ><Pencil :size="14" />{{ t('Edit description')
                }}<span class="coming-soon-badge">{{ t('Soon') }}</span></Button
            >
        </SurfaceCard>
        <SurfaceCard
            :title="t('Main attributes')"
            :description="t('Current value / genetic potential.')"
            class="pet-attributes"
        >
            <DogStats :values="pet.stats" variant="bars" />
        </SurfaceCard>
        <SurfaceCard class="pet-schedule">
            <template #header
                ><div class="pet-section-heading">
                    <h2>{{ t('Current activity') }}</h2>
                    <span class="coming-soon-badge">{{ t('Soon') }}</span>
                </div></template
            >
            <div class="pet-activity-placeholder">
                <span><Footprints :size="30" /></span>
                <div>
                    <strong>{{ t('A little adventure ahead') }}</strong>
                    <p>
                        {{
                            t(
                                'Walks, training and their progress will appear here.',
                            )
                        }}
                    </p>
                </div>
            </div>
            <div class="pet-section-heading pet-events-heading">
                <h2>{{ t('Upcoming events') }}</h2>
                <span class="coming-soon-badge">{{ t('Soon') }}</span>
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
                        }}<small>{{
                            t('No scheduled events yet')
                        }}</small></span
                    >
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled
                        :title="
                            t('{feature} — coming soon', {
                                feature: t(event.label),
                            })
                        "
                        ><CalendarDays
                            v-if="event.action === 'Schedule'"
                            :size="14"
                        />{{ t(event.action) }}</Button
                    >
                </li>
            </ul>
        </SurfaceCard>
    </div>
</template>
