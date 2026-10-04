<script setup lang="ts">
import {
    Award,
    BarChart3,
    CalendarDays,
    Coins,
    Dumbbell,
    GitBranch,
    Heart,
    PawPrint,
    Trophy,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import SurfaceCard from '@/components/SurfaceCard.vue';
import { useI18n } from '@/composables/useI18n';
import type { PlayerProfile } from '@/types/player';

const props = defineProps<{ player: PlayerProfile }>();
const { t, locale, number } = useI18n();
type StatisticKey = keyof Omit<PlayerProfile['statistics'], 'lastActionAt'>;
type Statistic = { label: string; value: number; coins?: boolean };
type StatisticGroup = { label: string; icon: Component; entries: Statistic[] };

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
const groups = computed<StatisticGroup[]>(() => {
    const metric = (
        label: string,
        key: StatisticKey,
        coins = false,
    ): Statistic => ({
        label,
        value: props.player.statistics[key],
        coins,
    });
    return [
        {
            label: 'Care together',
            icon: Heart,
            entries: [
                metric('Feedings completed', 'feedingCount'),
                metric('Waterings completed', 'wateringCount'),
                metric('Play sessions completed', 'playCount'),
                metric('Grooming sessions completed', 'groomingCount'),
                metric('Rests completed', 'restCount'),
                metric('Veterinary visits', 'veterinaryCount'),
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
                metric('Skill lessons completed', 'skillLessonsCount'),
                metric('Dog work shifts completed', 'workCount'),
            ],
        },
        {
            label: 'Competitions',
            icon: Trophy,
            entries: [
                metric('Competition starts', 'competitionStarts'),
                metric('Competition podiums', 'competitionPodiums'),
                metric('Competition wins', 'competitionWins'),
                metric('Agility wins', 'agilityWins'),
                metric('Nosework wins', 'noseworkWins'),
                metric('Canicross wins', 'canicrossWins'),
            ],
        },
        {
            label: 'Exhibitions',
            icon: Award,
            entries: [
                metric('Exhibition starts', 'exhibitionStarts'),
                metric('Exhibition podiums', 'exhibitionPodiums'),
                metric('Exhibition wins', 'exhibitionWins'),
                metric('Conformation wins', 'conformationWins'),
                metric('Progeny competition starts', 'progenyStarts'),
                metric('Progeny evaluation wins', 'progenyWins'),
            ],
        },
        {
            label: 'Titles and cups',
            icon: Award,
            entries: [
                metric('Titles earned', 'titlesCount'),
                metric('Dogs awarded titles', 'titledDogsCount'),
                metric('Weekly event cups', 'weeklyEventWins'),
                metric('Monthly event championships', 'monthlyEventWins'),
            ],
        },
        {
            label: 'Breeding and offspring',
            icon: GitBranch,
            entries: [
                metric('Litters started', 'littersStarted'),
                metric('Litters born', 'littersBorn'),
                metric('Puppies born', 'puppiesBorn'),
                metric('Puppies kept', 'puppiesKept'),
                metric('Puppies purchased', 'puppiesPurchased'),
                metric('Puppies sold', 'puppiesSold'),
                metric('Titled offspring bred', 'titledOffspring'),
            ],
        },
        {
            label: 'Event and breeding economy',
            icon: Coins,
            entries: [
                metric('Event prize money earned', 'eventPrizeCoins', true),
                metric('Event entry fees paid', 'eventFeesCoins', true),
                metric('Puppy sales revenue', 'puppySalesCoins', true),
                metric('Ammunition purchases', 'ammunitionPurchases'),
            ],
        },
    ];
});
</script>

<template>
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
            <section v-for="group in groups" :key="group.label">
                <h4>
                    <component
                        :is="group.icon"
                        :size="18"
                        aria-hidden="true"
                    />{{ t(group.label) }}
                </h4>
                <dl class="player-statistics-details">
                    <div v-for="stat in group.entries" :key="stat.label">
                        <dt>{{ t(stat.label) }}</dt>
                        <dd>
                            {{
                                stat.coins
                                    ? t('{amount} coins', {
                                          amount: number(stat.value),
                                      })
                                    : number(stat.value)
                            }}
                        </dd>
                    </div>
                </dl>
            </section>
        </div>
        <p class="player-statistics-hint">
            {{
                t(
                    'Statistics record your completed activities and earnings, including dogs and offspring that later change owners. Cancelled entries and NPCs do not count.',
                )
            }}
        </p>
        <p v-if="lastAction" class="player-last-action">
            <CalendarDays :size="17" aria-hidden="true" /><span
                >{{ t('Last dog activity') }}:
                <time :datetime="player.statistics.lastActionAt ?? undefined">{{
                    lastAction
                }}</time></span
            >
        </p>
    </SurfaceCard>
</template>
