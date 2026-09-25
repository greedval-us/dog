<script setup lang="ts">
import {
    Dumbbell,
    Footprints,
    Medal,
    PawPrint,
    Sparkles,
    Trophy,
} from '@lucide/vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { useI18n } from '@/composables/useI18n';
import type { PlayerProfile } from '@/types/player';

defineProps<{ player: PlayerProfile }>();
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
            <div class="player-card-heading">
                <div class="player-card-identity">
                    <span class="profile-avatar" aria-hidden="true">{{
                        player.username.charAt(0).toUpperCase()
                    }}</span>
                    <div>
                        <h2>{{ player.name }}</h2>
                        <p>@{{ player.username }}</p>
                    </div>
                </div>
                <span class="player-level"
                    ><Sparkles :size="16" aria-hidden="true" />{{
                        t('Level {level}', { level: number(player.level) })
                    }}</span
                >
            </div>
        </template>
        <div class="player-card-content">
            <section class="player-about" :aria-label="t('About me')">
                <h3>{{ t('About me') }}</h3>
                <p v-if="player.bio" class="player-bio">{{ player.bio }}</p>
                <p v-else class="field-hint">
                    {{ t('This player has not added a bio yet.') }}
                </p>
            </section>
            <dl class="player-statistics" :aria-label="t('Player statistics')">
                <div v-for="stat in statistics" :key="stat.key">
                    <dt>
                        <component
                            :is="stat.icon"
                            :size="18"
                            aria-hidden="true"
                        /><span>{{ t(stat.label) }}</span>
                    </dt>
                    <dd>{{ number(player[stat.key]) }}</dd>
                </div>
            </dl>
            <div v-if="$slots.actions" class="player-card-actions">
                <slot name="actions" />
            </div>
        </div>
    </SurfaceCard>
</template>
