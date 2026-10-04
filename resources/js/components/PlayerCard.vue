<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Crown, Leaf, Sparkles, Trophy } from '@lucide/vue';
import { computed } from 'vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import HelpHint from '@/components/HelpHint.vue';
import PlayerAvatar from '@/components/PlayerAvatar.vue';
import PlayerStatistics from '@/components/PlayerStatistics.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { petScene } from '@/routes';
import { achievements } from '@/routes/players';
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
const experienceFormatter = computed(() => new Intl.NumberFormat(locale.value));
const experience = (value: string): string =>
    experienceFormatter.value.format(BigInt(value));
const progressPercent = computed(() =>
    Math.max(0, Math.min(100, props.player.progress.percent)),
);
const progressText = computed(() =>
    t('{current} / {required} XP', {
        current: experience(props.player.progress.levelExperience),
        required: experience(props.player.progress.requiredExperience),
    }),
);
</script>

<template>
    <div class="player-card">
        <section class="player-card-hero" :aria-label="t('Player card')">
            <div class="player-card-cover">
                <img :src="petScene.url()" alt="" aria-hidden="true" />
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
                <div class="player-header-actions">
                    <Button as-child variant="secondary">
                        <Link :href="achievements(player.username)">
                            <Trophy :size="17" aria-hidden="true" />{{
                                t('Achievements')
                            }}
                        </Link>
                    </Button>
                    <slot name="header-actions" />
                </div>
            </div>
        </section>
        <div class="player-card-panels">
            <SurfaceCard class="player-progress-section">
                <h3>
                    <Sparkles :size="24" aria-hidden="true" />{{
                        t('Player progress')
                    }}
                    <HelpHint
                        :label="t('Player progress')"
                        :text="
                            t(
                                'Completed dog activities earn experience. Each next level needs twice as much XP: 100, 200, 400…',
                            )
                        "
                    />
                </h3>
                <div class="player-progress-heading">
                    <span>{{
                        t('To level {level}', {
                            level: number(player.progress.nextLevel),
                        })
                    }}</span>
                    <strong>{{ progressText }}</strong>
                </div>
                <div
                    class="player-progress-track"
                    role="progressbar"
                    :aria-label="
                        t('Progress to level {level}', {
                            level: number(player.progress.nextLevel),
                        })
                    "
                    :aria-valuenow="progressPercent"
                    :aria-valuemin="0"
                    :aria-valuemax="100"
                    :aria-valuetext="progressText"
                    :style="{ '--player-progress': `${progressPercent}%` }"
                >
                    <span />
                </div>
                <p class="player-progress-remaining">
                    {{
                        t('Another {amount} XP to level {level}', {
                            amount: experience(
                                player.progress.remainingExperience,
                            ),
                            level: number(player.progress.nextLevel),
                        })
                    }}
                </p>
                <dl class="player-progress-total">
                    <dt>{{ t('Total experience') }}</dt>
                    <dd>
                        {{
                            t('{amount} XP', {
                                amount: experience(player.experience),
                            })
                        }}
                    </dd>
                </dl>
            </SurfaceCard>
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
        </div>
        <PlayerStatistics :player="player" />
        <slot name="daily-work" />
        <div v-if="$slots.actions" class="player-card-actions">
            <slot name="actions" />
        </div>
    </div>
</template>
