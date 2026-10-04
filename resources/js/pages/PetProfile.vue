<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Flower2,
    GitBranch,
    Heart,
    Leaf,
    Mars,
    ShieldCheck,
    Trophy,
    Venus,
} from '@lucide/vue';
import { computed } from 'vue';
import DogStats from '@/components/DogStats.vue';
import GameAssetArtwork from '@/components/GameAssetArtwork.vue';
import Heading from '@/components/Heading.vue';
import HelpHint from '@/components/HelpHint.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { sizeLabels } from '@/lib/petLabels';
import { dashboard } from '@/routes';
import { pedigree } from '@/routes/pets';
import type { PetPublicProfile } from '@/types/pet-pedigree';

defineProps<{ profile: PetPublicProfile }>();
const { t, locale, number } = useI18n();
const page = usePage();
const originPedigreeId = computed(() => {
    const value = new URL(page.url, 'http://localhost').searchParams.get(
        'from_pedigree',
    );
    if (value === null || !/^[1-9]\d{0,17}$/.test(value)) {
        return null;
    }
    const id = Number(value);
    return Number.isSafeInteger(id) ? id : null;
});
const date = (value: string): string =>
    new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(value));
const traits: Record<string, string> = {
    friendly: 'Friendly',
    active: 'Active',
    loyal: 'Loyal',
    smart: 'Smart',
    fast_learner: 'Quick learner',
};
</script>

<template>
    <div class="pet-public-page">
        <Head :title="t('Dog card — {name}', { name: profile.name })" />
        <div class="pet-public-heading">
            <Heading :title="t('Dog card')" />
            <Button as-child variant="secondary">
                <Link
                    :href="
                        originPedigreeId
                            ? pedigree(originPedigreeId)
                            : dashboard()
                    "
                    ><ArrowLeft :size="17" aria-hidden="true" />{{
                        t(
                            originPedigreeId
                                ? 'Back to pedigree'
                                : 'Back to my dog',
                        )
                    }}</Link
                >
            </Button>
        </div>
        <SurfaceCard class="pet-public-identity">
            <div class="pet-public-artwork">
                <GameAssetArtwork :asset-id="null" />
            </div>
            <div class="pet-public-name">
                <h2>{{ profile.name }}</h2>
                <p class="pet-public-breed">
                    <component
                        :is="profile.sex === 'male' ? Mars : Venus"
                        :size="20"
                        aria-hidden="true"
                    />{{ profile.breed }}
                </p>
                <div class="pet-tags">
                    <span
                        ><CalendarDays :size="15" aria-hidden="true" />{{
                            t('Born {date}', { date: date(profile.bornAt) })
                        }}</span
                    >
                    <span>{{
                        t(profile.sex === 'male' ? 'Male' : 'Female')
                    }}</span>
                    <span v-if="profile.isPurebred"
                        ><ShieldCheck :size="15" aria-hidden="true" />{{
                            t('Purebred')
                        }}</span
                    >
                    <span v-else>{{ t('Mixed breed') }}</span>
                </div>
                <span
                    v-if="profile.lifecycle.status !== 'active'"
                    class="pet-lifecycle-status"
                    :class="'is-' + profile.lifecycle.status"
                >
                    <component
                        :is="
                            profile.lifecycle.status === 'retired'
                                ? Leaf
                                : Flower2
                        "
                        :size="16"
                        aria-hidden="true"
                    />{{
                        t(
                            profile.lifecycle.status === 'retired'
                                ? 'Retired'
                                : 'In loving memory',
                        )
                    }}
                </span>
            </div>
            <Button
                v-if="profile.hasPedigree"
                as-child
                variant="secondary"
                class="pet-public-pedigree"
            >
                <Link :href="pedigree(profile.id)"
                    ><GitBranch :size="18" aria-hidden="true" />{{
                        t('View pedigree')
                    }}</Link
                >
            </Button>
        </SurfaceCard>
        <div class="pet-public-details">
            <SurfaceCard :title="t('About the dog')">
                <p v-if="profile.description" class="pet-description">
                    {{ profile.description }}
                </p>
                <dl class="pet-public-facts">
                    <div>
                        <dt>{{ t('Coat color') }}</dt>
                        <dd>{{ profile.coatColor }}</dd>
                    </div>
                    <div>
                        <dt>{{ t('Size') }}</dt>
                        <dd>{{ t(sizeLabels[profile.size]) }}</dd>
                    </div>
                    <div>
                        <dt>{{ t('Generation') }}</dt>
                        <dd>{{ number(profile.generation) }}</dd>
                    </div>
                    <div v-if="profile.lifecycle.archivedAt">
                        <dt>
                            {{
                                t(
                                    profile.lifecycle.status === 'retired'
                                        ? 'Retirement date'
                                        : 'Date of passing',
                                )
                            }}
                        </dt>
                        <dd>
                            <time :datetime="profile.lifecycle.archivedAt">{{
                                date(profile.lifecycle.archivedAt)
                            }}</time>
                        </dd>
                    </div>
                </dl>
                <div v-if="profile.traits.length" class="pet-traits">
                    <span v-for="trait in profile.traits" :key="trait"
                        ><Heart :size="14" aria-hidden="true" />{{
                            t(traits[trait] ?? trait)
                        }}</span
                    >
                </div>
                <p v-if="!profile.hasPedigree" class="pet-public-no-pedigree">
                    {{ t('No known ancestors yet') }}
                </p>
            </SurfaceCard>
            <SurfaceCard :title="t('Main attributes')">
                <template #header>
                    <div class="surface-heading-help">
                        <h2>{{ t('Main attributes') }}</h2>
                        <HelpHint
                            :label="t('Main attributes')"
                            :text="t('Current value / genetic potential.')"
                        />
                    </div>
                </template>
                <DogStats :values="profile.stats" variant="bars" />
            </SurfaceCard>
        </div>
        <div
            v-if="profile.exterior || profile.titles?.length"
            class="pet-public-credentials"
        >
            <SurfaceCard
                v-if="profile.exterior"
                :title="t('Breed conformation')"
            >
                <dl class="event-exterior-values">
                    <div v-for="(value, key) in profile.exterior" :key="key">
                        <dt>{{ t(`events.exterior.${key}`) }}</dt>
                        <dd>{{ number(value) }} / 100</dd>
                    </div>
                </dl>
            </SurfaceCard>
            <SurfaceCard v-if="profile.titles?.length" :title="t('Dog titles')">
                <ul class="event-title-list">
                    <li
                        v-for="(title, titleIndex) in profile.titles"
                        :key="titleIndex"
                    >
                        <Trophy :size="18" aria-hidden="true" /><span
                            >{{ title.name
                            }}<small
                                >{{
                                    t(`events.discipline.${title.discipline}`)
                                }}
                                ·
                                {{ t(`events.frequency.${title.frequency}`) }} ·
                                {{ date(title.awardedAt) }}</small
                            ></span
                        >
                    </li>
                </ul>
            </SurfaceCard>
        </div>
    </div>
</template>
