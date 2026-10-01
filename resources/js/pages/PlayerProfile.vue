<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Package, PawPrint, Pencil } from '@lucide/vue';
import GameAssetArtwork from '@/components/GameAssetArtwork.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import Heading from '@/components/Heading.vue';
import PlayerCard from '@/components/PlayerCard.vue';
import PlayerDailyWork from '@/components/PlayerDailyWork.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { edit } from '@/routes/profile';
import { dashboard } from '@/routes';
import { image } from '@/routes/assets';
import { index as kennel } from '@/routes/kennel';
import { index as inventory } from '@/routes/inventory';
import { index as dogWork } from '@/routes/dog-work';
import type { DailyWork, PlayerDog, PlayerProfile } from '@/types/player';

defineProps<{
    player: PlayerProfile;
    isOwner: boolean;
    inventoryCount: number | null;
    dogs: PlayerDog[];
    dailyWork: DailyWork | null;
}>();
const { t, number } = useI18n();
</script>

<template>
    <div class="player-profile-page">
        <Head
            :title="
                t('Player card — {username}', { username: player.username })
            "
        />
        <Heading :title="t('Player card')" />
        <PlayerCard :player="player">
            <template v-if="isOwner" #header-actions>
                <Button as-child variant="secondary">
                    <Link :href="inventory()"
                        ><Package aria-hidden="true" />{{ t('Inventory')
                        }}<span class="inventory-link-count">{{
                            number(inventoryCount ?? 0)
                        }}</span></Link
                    >
                </Button>
                <Button as-child
                    ><Link :href="edit()"
                        ><Pencil />{{ t('Edit profile') }}</Link
                    ></Button
                >
            </template>
            <template v-if="isOwner && dailyWork" #daily-work>
                <PlayerDailyWork :work="dailyWork" />
                <Button as-child variant="secondary">
                    <Link :href="dogWork()"
                        ><PawPrint aria-hidden="true" />{{
                            t('Work with a dog')
                        }}</Link
                    >
                </Button>
            </template>
        </PlayerCard>
        <SurfaceCard class="player-dogs">
            <template #header
                ><h2>
                    <PawPrint :size="25" aria-hidden="true" />{{
                        isOwner ? t('My dogs') : t('Dogs in care')
                    }}
                </h2></template
            >
            <div v-if="dogs.length" class="player-dogs-grid">
                <article v-for="dog in dogs" :key="dog.id" class="player-dog">
                    <div class="player-dog-picture">
                        <img
                            v-if="dog.backgroundId"
                            class="player-dog-landscape"
                            :src="
                                image.url({
                                    asset: dog.backgroundId,
                                    variant: 'image',
                                })
                            "
                            alt=""
                            aria-hidden="true"
                        />
                        <GameAssetArtwork
                            :asset-id="dog.portraitId"
                            :alt="
                                t('Illustration of {breed}', {
                                    breed: dog.breed,
                                })
                            "
                        />
                    </div>
                    <div class="player-dog-info">
                        <h3>{{ dog.name }}</h3>
                        <p>{{ dog.breed }}</p>
                        <Button
                            v-if="isOwner"
                            as-child
                            variant="secondary"
                            size="sm"
                            ><Link
                                :href="dashboard({ query: { pet: dog.id } })"
                                :aria-label="
                                    t('Open {name}', { name: dog.name })
                                "
                                >{{ t('Open dog')
                                }}<ArrowRight :size="16" /></Link
                        ></Button>
                    </div>
                </article>
            </div>
            <div v-else class="player-dogs-empty">
                <p>{{ t('There are currently no dogs in your care.') }}</p>
                <Button v-if="isOwner" as-child variant="secondary"
                    ><Link :href="kennel()"
                        >{{ t('Visit the kennel') }}<ArrowRight /></Link
                ></Button>
            </div>
        </SurfaceCard>
    </div>
</template>
