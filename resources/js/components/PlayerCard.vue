<script setup lang="ts">
import {
    BarChart3,
    CalendarDays,
    Crown,
    Dumbbell,
    Footprints,
    Leaf,
    PawPrint,
    Sparkles,
    Trophy,
} from '@lucide/vue';
import { computed } from 'vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import PlayerAvatar from '@/components/PlayerAvatar.vue';
import { useI18n } from '@/composables/useI18n';
import { petScene } from '@/routes';
import type { PlayerProfile } from '@/types/player';

const props = defineProps<{ player: PlayerProfile }>();
const { t, locale, number } = useI18n();
const joined = computed(() =>
    props.player.joinedAt
        ? new Intl.DateTimeFormat(locale.value, {
              month: 'long',
              year: 'numeric',
              day: 'numeric',
          }).format(new Date(props.player.joinedAt + 'T12:00:00'))
        : null,
);
const statistics = computed(() => [
    { label: 'Dogs in care', value: props.player.dogsCount, icon: PawPrint },
    {
        label: 'Wins',
        value: props.player.exhibitionWins + props.player.competitionWins,
        icon: Trophy,
    },
    {
        label: 'Training sessions completed',
        value: props.player.trainingsCount,
        icon: Dumbbell,
    },
    {
        label: 'Walks completed',
        value: props.player.walksCount,
        icon: Footprints,
    },
]);
</script>

<template>
    <div class="player-card">
        <section class="player-card-hero" :aria-label="t('Player card')">
            <div class="player-card-cover">
                <img :src="petScene.url()" alt="" aria-hidden="true" />
                <span>{{ t('Good dogs make the world kinder') }}</span>
            </div>
            <div class="player-card-heading">
                <div class="player-card-identity">
                    <div class="player-card-portrait">
                        <PlayerAvatar
                            :username="player.username"
                            :version="player.avatarVersion"
                            size="large"
                        />
                    </div>
                    <div class="player-card-name">
                        <h2>{{ player.username }}</h2>
                        <p>{{ player.name }}</p>
                    </div>
                    <span class="player-level"
                        ><Crown :size="20" aria-hidden="true" />{{
                            t('Level {level}', { level: number(player.level) })
                        }}</span
                    >
                </div>
                <div
                    v-if="$slots['header-actions']"
                    class="player-header-actions"
                >
                    <slot name="header-actions" />
                </div>
            </div>
        </section>
        <div class="player-card-panels">
            <SurfaceCard class="player-about">
                <h3>
                    <Leaf :size="24" aria-hidden="true" />{{ t('About me') }}
                </h3>
                <p v-if="player.bio" class="player-bio">{{ player.bio }}</p>
                <p v-else class="player-bio player-bio-empty">
                    {{ t('This player has not added a bio yet.') }}
                </p>
                <p v-if="joined" class="player-joined">
                    <CalendarDays :size="19" aria-hidden="true" />{{
                        t('Playing since {date}', { date: joined })
                    }}
                </p>
            </SurfaceCard>
            <SurfaceCard class="player-statistics-section">
                <h3>
                    <BarChart3 :size="24" aria-hidden="true" />{{
                        t('Player statistics')
                    }}
                </h3>
                <dl class="player-statistics">
                    <div v-for="stat in statistics" :key="stat.label">
                        <dt>
                            <component
                                :is="stat.icon"
                                :size="25"
                                aria-hidden="true"
                            /><span>{{ t(stat.label) }}</span>
                        </dt>
                        <dd>{{ number(stat.value) }}</dd>
                    </div>
                </dl>
                <div class="player-statistics-extra">
                    <span
                        ><Sparkles :size="15" aria-hidden="true" />{{
                            t('Player experience')
                        }}: {{ number(player.experience) }}</span
                    >
                    <span
                        >{{ t('Exhibition wins') }}:
                        {{ number(player.exhibitionWins) }}</span
                    >
                    <span
                        >{{ t('Competition wins') }}:
                        {{ number(player.competitionWins) }}</span
                    >
                </div>
            </SurfaceCard>
        </div>
        <slot name="daily-work" />
        <div v-if="$slots.actions" class="player-card-actions">
            <slot name="actions" />
        </div>
    </div>
</template>
