<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Flower2, Leaf, PawPrint } from '@lucide/vue';
import { ref } from 'vue';
import CursorPagination from '@/components/CursorPagination.vue';
import GameAssetArtwork from '@/components/GameAssetArtwork.vue';
import Heading from '@/components/Heading.vue';
import HelpHint from '@/components/HelpHint.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { image } from '@/routes/assets';
import { show as playerProfile } from '@/routes/players';
import { index, show } from '@/routes/players/memorial';
import type { PetMemorialList } from '@/types/pet-memorial';
import type { PlayerProfile } from '@/types/player';

const props = defineProps<{
    player: PlayerProfile;
    isOwner: boolean;
    pets: PetMemorialList;
}>();
const { t, locale } = useI18n();
const loading = ref(false);
const pageLink = (cursor: string): string =>
    index.url(props.player.username, { query: { cursor } });
const date = (value: string): string =>
    new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
</script>

<template>
    <div class="pet-memorial-page">
        <Head
            :title="
                t('Pet memorial hall — {username}', {
                    username: player.username,
                })
            "
        />
        <div class="pet-memorial-heading">
            <div class="page-heading-with-help">
                <Heading :title="t('Pet memorial hall')" />
                <HelpHint
                    :label="t('Pet memorial hall')"
                    :text="
                        t(
                            'Faithful companions stay part of your story. Cards of retired dogs and dogs who have passed away are preserved here.',
                        )
                    "
                />
            </div>
            <Button as-child variant="secondary">
                <Link :href="playerProfile(player.username)">
                    <ArrowLeft :size="17" aria-hidden="true" />{{
                        t('Back to player card')
                    }}
                </Link>
            </Button>
        </div>
        <p class="pet-memorial-owner">
            {{ t('Companions of {username}', { username: player.username }) }}
        </p>
        <div v-if="pets.data.length" class="player-dogs-grid">
            <SurfaceCard
                v-for="pet in pets.data"
                :key="pet.id"
                class="pet-memorial-card"
            >
                <div class="player-dog-picture">
                    <img
                        v-if="pet.backgroundId"
                        class="player-dog-landscape"
                        :src="
                            image.url({
                                asset: pet.backgroundId,
                                variant: 'image',
                            })
                        "
                        alt=""
                        aria-hidden="true"
                        decoding="async"
                        loading="lazy"
                    />
                    <GameAssetArtwork
                        :asset-id="pet.portraitId"
                        :alt="
                            t('Illustration of {breed}', { breed: pet.breed })
                        "
                        lazy
                    />
                </div>
                <div class="pet-memorial-card-content">
                    <span
                        class="pet-lifecycle-status"
                        :class="'is-' + pet.status"
                    >
                        <component
                            :is="pet.status === 'retired' ? Leaf : Flower2"
                            :size="16"
                            aria-hidden="true"
                        />
                        {{
                            t(
                                pet.status === 'retired'
                                    ? 'Retired'
                                    : 'In loving memory',
                            )
                        }}
                    </span>
                    <h2>{{ pet.name }}</h2>
                    <p>{{ pet.breed }}</p>
                    <dl class="pet-memorial-dates">
                        <div>
                            <dt>{{ t('Date of birth') }}</dt>
                            <dd>
                                <time :datetime="pet.bornAt">{{
                                    date(pet.bornAt)
                                }}</time>
                            </dd>
                        </div>
                        <div>
                            <dt>
                                {{
                                    t(
                                        pet.status === 'retired'
                                            ? 'Retirement date'
                                            : 'Date of passing',
                                    )
                                }}
                            </dt>
                            <dd>
                                <time :datetime="pet.archivedAt">{{
                                    date(pet.archivedAt)
                                }}</time>
                            </dd>
                        </div>
                    </dl>
                    <Button as-child variant="secondary" size="sm">
                        <Link
                            :href="show({ user: player.username, pet: pet.id })"
                            :aria-label="t('Open {name}', { name: pet.name })"
                        >
                            {{ t('View dog card')
                            }}<ArrowRight :size="16" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>
            </SurfaceCard>
        </div>
        <SurfaceCard v-else class="pet-memorial-empty">
            <PawPrint :size="40" :stroke-width="1.25" aria-hidden="true" />
            <h2>{{ t('Every shared story is precious') }}</h2>
            <p>
                {{
                    t(
                        isOwner
                            ? 'Your memorial hall is empty. Retired dogs and dogs who have passed away will appear here.'
                            : 'This player has no pets in the memorial hall yet.',
                    )
                }}
            </p>
        </SurfaceCard>
        <CursorPagination
            :previous-cursor="pets.previousCursor"
            :next-cursor="pets.nextCursor"
            :page-link="pageLink"
            :label="t('Pet memorial pages')"
            :disabled="loading"
            @start="loading = true"
            @finish="loading = false"
        />
    </div>
</template>
