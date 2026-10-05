<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Flower2, GitBranch, Leaf, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import HelpHint from '@/components/HelpHint.vue';
import PetCondition from '@/components/PetCondition.vue';
import PetHero from '@/components/PetHero.vue';
import PetOverview from '@/components/PetOverview.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { image } from '@/routes/assets';
import { index } from '@/routes/players/memorial';
import { pedigree } from '@/routes/pets';
import type { PetAppearance } from '@/types/appearance';
import type { PlayerPet } from '@/types/pet';
import type { PlayerProfile } from '@/types/player';

const props = defineProps<{
    player: PlayerProfile;
    isOwner: boolean;
    pet: PlayerPet;
    appearance: PetAppearance;
    learnedSkills: {
        id: number;
        name: string;
        description: string | null;
        level: number;
    }[];
}>();
const { t, locale, number } = useI18n();
const archivedDate = computed(() =>
    props.pet.lifecycle.archivedAt
        ? new Intl.DateTimeFormat(locale.value, {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          }).format(new Date(props.pet.lifecycle.archivedAt))
        : null,
);
</script>

<template>
    <div class="my-dog-page pet-memorial-page">
        <Head :title="t('{name} — Pet memorial hall', { name: pet.name })" />
        <div class="pet-memorial-heading">
            <Heading
                :title="t('Pet memorial hall')"
                :description="
                    t('Companions of {username}', { username: player.username })
                "
            />
            <Button as-child variant="secondary">
                <Link :href="index(player.username)">
                    <ArrowLeft :size="17" aria-hidden="true" />{{
                        t('Back to memorial hall')
                    }}
                </Link>
            </Button>
        </div>
        <SurfaceCard class="pet-memorial-notice">
            <component
                :is="pet.lifecycle.status === 'retired' ? Leaf : Flower2"
                :size="27"
                aria-hidden="true"
            />
            <div>
                <h2>
                    {{
                        t(
                            pet.lifecycle.status === 'retired'
                                ? 'A well-earned rest'
                                : 'Always remembered',
                        )
                    }}
                </h2>
                <p v-if="archivedDate">
                    {{
                        t(
                            pet.lifecycle.status === 'retired'
                                ? '{name} retired on {date}.'
                                : '{name} passed away on {date}.',
                            { name: pet.name, date: archivedDate },
                        )
                    }}
                </p>
                <p>
                    {{
                        t(
                            'This card preserves the dog’s characteristics as they were. The dog no longer takes up a slot and cannot return to active play.',
                        )
                    }}
                </p>
            </div>
        </SurfaceCard>
        <div class="pet-dossier pet-dossier-compact">
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
                decoding="async"
            />
            <div class="pet-stage">
                <PetHero :pet="pet" :appearance="appearance" read-only />
                <aside
                    class="pet-sidebar"
                    :aria-label="t('Preserved wellbeing')"
                >
                    <PetCondition :states="pet.states" read-only />
                </aside>
            </div>
            <PetOverview :pet="pet" :care="null" read-only />
            <Button
                v-if="pet.hasPedigree"
                as-child
                variant="secondary"
                class="pet-memorial-pedigree"
            >
                <Link :href="pedigree(pet.id)"
                    ><GitBranch :size="17" aria-hidden="true" />{{
                        t('View pedigree')
                    }}</Link
                >
            </Button>
            <SurfaceCard
                v-if="learnedSkills.length"
                class="pet-memorial-skills"
            >
                <template #header
                    ><h2>
                        <Sparkles :size="22" aria-hidden="true" />{{
                            t('Learned skills')
                        }}
                    </h2></template
                >
                <ul>
                    <li v-for="skill in learnedSkills" :key="skill.id">
                        <div>
                            <div class="surface-heading-help">
                                <h3>{{ skill.name }}</h3>
                                <HelpHint
                                    v-if="skill.description"
                                    :label="skill.name"
                                    :text="skill.description"
                                />
                            </div>
                        </div>
                        <span>{{
                            t('Level {level} / 5', {
                                level: number(skill.level),
                            })
                        }}</span>
                    </li>
                </ul>
            </SurfaceCard>
        </div>
    </div>
</template>
