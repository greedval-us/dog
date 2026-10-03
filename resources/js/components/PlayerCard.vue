<script setup lang="ts">
import {
    BarChart3,
    CalendarDays,
    Crown,
    Dumbbell,
    Heart,
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
const lastAction = computed(() =>
    props.player.statistics.lastActionAt
        ? new Intl.DateTimeFormat(locale.value, {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(props.player.statistics.lastActionAt))
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
const highlights = computed(() => [
    {
        label: 'Actions completed',
        value: props.player.statistics.actionsCount,
        icon: Heart,
    },
    {
        label: 'Days with dog activities',
        value: props.player.statistics.activeDays,
        icon: CalendarDays,
    },
    { label: 'Dogs in care', value: props.player.dogsCount, icon: PawPrint },
    {
        label: 'Wins',
        value: props.player.exhibitionWins + props.player.competitionWins,
        icon: Trophy,
    },
]);
const statisticGroups = computed(() => [
    {
        label: 'Care together',
        icon: Heart,
        entries: [
            {
                label: 'Feedings completed',
                value: props.player.statistics.feedingCount,
            },
            {
                label: 'Waterings completed',
                value: props.player.statistics.wateringCount,
            },
            {
                label: 'Play sessions completed',
                value: props.player.statistics.playCount,
            },
            {
                label: 'Grooming sessions completed',
                value: props.player.statistics.groomingCount,
            },
            {
                label: 'Rests completed',
                value: props.player.statistics.restCount,
            },
            {
                label: 'Veterinary visits',
                value: props.player.statistics.veterinaryCount,
            },
        ],
    },
    {
        label: 'Activities and achievements',
        icon: Dumbbell,
        entries: [
            { label: 'Walks completed', value: props.player.walksCount },
            {
                label: 'Training sessions completed',
                value: props.player.trainingsCount,
            },
            {
                label: 'Skill lessons completed',
                value: props.player.statistics.skillLessonsCount,
            },
            {
                label: 'Dog work shifts completed',
                value: props.player.statistics.workCount,
            },
            { label: 'Exhibition wins', value: props.player.exhibitionWins },
            { label: 'Competition wins', value: props.player.competitionWins },
        ],
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
            <SurfaceCard class="player-progress-section">
                <h3>
                    <Sparkles :size="24" aria-hidden="true" />{{
                        t('Player progress')
                    }}
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
                <p class="player-progress-hint">
                    {{
                        t(
                            'Completed dog activities earn experience. Each next level needs twice as much XP: 100, 200, 400…',
                        )
                    }}
                </p>
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
        <SurfaceCard class="player-statistics-section">
            <div class="player-statistics-heading">
                <h3>
                    <BarChart3 :size="24" aria-hidden="true" />{{
                        t('Player statistics')
                    }}
                </h3>
                <span>{{ t('Across all dogs, all time') }}</span>
            </div>
            <dl class="player-statistics">
                <div v-for="stat in highlights" :key="stat.label">
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
            <div class="player-statistics-groups">
                <section v-for="group in statisticGroups" :key="group.label">
                    <h4>
                        <component
                            :is="group.icon"
                            :size="18"
                            aria-hidden="true"
                        />
                        {{ t(group.label) }}
                    </h4>
                    <dl class="player-statistics-details">
                        <div v-for="stat in group.entries" :key="stat.label">
                            <dt>{{ t(stat.label) }}</dt>
                            <dd>{{ number(stat.value) }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
            <p v-if="lastAction" class="player-last-action">
                <CalendarDays :size="17" aria-hidden="true" />
                <span
                    >{{ t('Last dog activity') }}:
                    <time
                        :datetime="player.statistics.lastActionAt ?? undefined"
                        >{{ lastAction }}</time
                    >
                </span>
            </p>
        </SurfaceCard>
        <slot name="daily-work" />
        <div v-if="$slots.actions" class="player-card-actions">
            <slot name="actions" />
        </div>
    </div>
</template>
