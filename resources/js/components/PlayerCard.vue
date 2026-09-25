<script setup lang="ts">
import {
    Camera,
    ChevronDown,
    Dumbbell,
    Footprints,
    Medal,
    PawPrint,
    Sparkles,
    Trophy,
} from '@lucide/vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import PlayerAvatar from '@/components/PlayerAvatar.vue';
import PlayerAvatarForm from '@/components/PlayerAvatarForm.vue';
import { useI18n } from '@/composables/useI18n';
import type { AvatarLimits, PlayerProfile } from '@/types/player';

defineProps<{ player: PlayerProfile; avatarLimits?: AvatarLimits | null }>();
const { t, locale } = useI18n();
const number = (value: number) =>
    new Intl.NumberFormat(locale.value).format(value);
const statistics = [
    { key: 'dogsCount', label: 'Dogs in care', icon: PawPrint },
    { key: 'experience', label: 'Player experience', icon: Sparkles },
    { key: 'exhibitionWins', label: 'Exhibition wins', icon: Trophy },
    { key: 'competitionWins', label: 'Competition wins', icon: Medal },
    { key: 'walksCount', label: 'Walks completed', icon: Footprints },
    {
        key: 'trainingsCount',
        label: 'Training sessions completed',
        icon: Dumbbell,
    },
] as const;
</script>

<template>
    <SurfaceCard class="player-card">
        <template #header>
            <div class="player-card-cover" aria-hidden="true">
                <PawPrint class="player-cover-paw" :stroke-width="1" />
                <PawPrint class="player-cover-paw-small" :stroke-width="1" />
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
                        <h2>{{ player.name }}</h2>
                        <p>@{{ player.username }}</p>
                        <span class="player-level">
                            <Sparkles :size="14" aria-hidden="true" />
                            {{
                                t('Level {level}', {
                                    level: number(player.level),
                                })
                            }}
                        </span>
                    </div>
                </div>
            </div>
        </template>
        <div class="player-card-content">
            <section class="player-about" :aria-label="t('About me')">
                <h3>{{ t('About me') }}</h3>
                <p v-if="player.bio" class="player-bio">{{ player.bio }}</p>
                <p v-else class="player-bio player-bio-empty">
                    {{ t('This player has not added a bio yet.') }}
                </p>
            </section>
            <section
                class="player-statistics-section"
                :aria-label="t('Player statistics')"
            >
                <h3>{{ t('Player statistics') }}</h3>
                <dl class="player-statistics">
                    <div
                        v-for="stat in statistics"
                        :key="stat.key"
                        :class="{
                            'player-stat-care': stat.key === 'dogsCount',
                        }"
                    >
                        <dt>
                            <span>{{ t(stat.label) }}</span>
                            <span class="player-stat-icon" aria-hidden="true">
                                <component :is="stat.icon" :size="19" />
                            </span>
                        </dt>
                        <dd>{{ number(player[stat.key]) }}</dd>
                    </div>
                </dl>
            </section>
            <details v-if="avatarLimits" class="player-avatar-editor">
                <summary>
                    <Camera :size="19" aria-hidden="true" />
                    <span>{{
                        player.avatarVersion
                            ? t('Change avatar')
                            : t('Add avatar')
                    }}</span>
                    <ChevronDown
                        class="player-avatar-chevron"
                        :size="18"
                        aria-hidden="true"
                    />
                </summary>
                <PlayerAvatarForm
                    :limits="avatarLimits"
                    :has-avatar="player.avatarVersion !== null"
                />
            </details>
            <div v-if="$slots.actions" class="player-card-actions">
                <slot name="actions" />
            </div>
        </div>
    </SurfaceCard>
</template>
