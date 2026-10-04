<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, LockKeyhole, Trophy } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import HelpHint from '@/components/HelpHint.vue';
import PlayerAvatar from '@/components/PlayerAvatar.vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { show as playerProfile } from '@/routes/players';
import type { PlayerAchievement } from '@/types/achievement';
import type { PlayerProfile } from '@/types/player';

defineProps<{
    player: PlayerProfile;
    achievements: PlayerAchievement[];
    unlockedCount: number;
    totalCount: number;
}>();

const { t, locale, number } = useI18n();
const date = (value: string): string =>
    new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
</script>

<template>
    <div class="achievements-page">
        <Head
            :title="
                t('Achievements — {username}', { username: player.username })
            "
        />
        <div class="achievements-heading">
            <div class="page-heading-with-help">
                <Heading :title="t('Achievements')" />
                <HelpHint
                    :label="t('Achievements')"
                    :text="
                        t(
                            'Small adventures, big memories. Collect achievements as you care for your dogs.',
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

        <SurfaceCard class="achievements-summary">
            <div class="achievements-player">
                <PlayerAvatar
                    :username="player.username"
                    :version="player.avatarVersion"
                />
                <div class="surface-heading-help">
                    <h2>
                        {{
                            t('Collection of {username}', {
                                username: player.username,
                            })
                        }}
                    </h2>
                    <HelpHint
                        :label="t('Achievements')"
                        :text="
                            t(
                                'Achievements give no rewards or bonuses. Just a reason to smile.',
                            )
                        "
                    />
                </div>
            </div>
            <div class="achievements-total">
                <Trophy :size="23" aria-hidden="true" />
                <div>
                    <strong
                        >{{ number(unlockedCount)
                        }}<span> / {{ number(totalCount) }}</span></strong
                    >
                    <span>{{ t('Achievements unlocked') }}</span>
                </div>
            </div>
        </SurfaceCard>

        <div v-if="achievements.length" class="achievements-grid">
            <SurfaceCard
                v-for="achievement in achievements"
                :key="achievement.id"
                class="achievement-card"
                :class="{ 'is-unlocked': achievement.unlockedAt }"
            >
                <div class="achievement-heading">
                    <img
                        class="achievement-artwork"
                        :src="achievement.imageUrl"
                        alt=""
                        aria-hidden="true"
                        width="80"
                        height="80"
                        loading="lazy"
                    />
                    <span class="achievement-status">
                        <component
                            :is="achievement.unlockedAt ? Check : LockKeyhole"
                            :size="14"
                            aria-hidden="true"
                        />
                        {{
                            t(
                                achievement.unlockedAt
                                    ? 'Achievement unlocked'
                                    : 'Not unlocked yet',
                            )
                        }}
                    </span>
                </div>
                <div class="achievement-story surface-heading-help">
                    <h2>{{ achievement.name }}</h2>
                    <HelpHint
                        v-if="achievement.description"
                        :label="achievement.name"
                        :text="achievement.description"
                    />
                </div>
                <div class="achievement-rule">
                    <h3>{{ t('How to unlock') }}</h3>
                    <p>{{ achievement.rule }}</p>
                </div>
                <div class="achievement-progress">
                    <div class="achievement-progress-heading">
                        <span>{{ t('Progress') }}</span>
                        <strong
                            >{{ number(achievement.progress) }} /
                            {{ number(achievement.target) }}</strong
                        >
                    </div>
                    <progress
                        :value="achievement.progress"
                        :max="achievement.target"
                        :aria-label="
                            t('Achievement progress: {name}', {
                                name: achievement.name,
                            })
                        "
                    >
                        {{ number(achievement.progress) }} /
                        {{ number(achievement.target) }}
                    </progress>
                    <p v-if="achievement.unlockedAt" class="achievement-date">
                        <Check :size="14" aria-hidden="true" />
                        {{
                            t('Unlocked on {date}', {
                                date: date(achievement.unlockedAt),
                            })
                        }}
                    </p>
                </div>
            </SurfaceCard>
        </div>
        <SurfaceCard v-else class="achievements-empty">
            <Trophy :size="36" aria-hidden="true" />
            <h2>{{ t('New adventures are on their way') }}</h2>
            <p>{{ t('Achievements will appear here soon.') }}</p>
        </SurfaceCard>
    </div>
</template>
