import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import PlayerStatistics from './PlayerStatistics.vue';
import type { PlayerProfile } from '@/types/player';

function playerFixture(): PlayerProfile {
    return {
        name: 'Player',
        username: 'player',
        avatarVersion: null,
        bio: null,
        level: 1,
        experience: '0',
        progress: {
            levelExperience: '0',
            requiredExperience: '100',
            remainingExperience: '100',
            percent: 0,
            nextLevel: 2,
        },
        statistics: {
            actionsCount: 50,
            feedingCount: 12,
            wateringCount: 10,
            playCount: 5,
            groomingCount: 2,
            restCount: 3,
            skillLessonsCount: 3,
            workCount: 2,
            veterinaryCount: 1,
            activeDays: 4,
            lastActionAt: '2026-10-04T12:00:00Z',
            competitionStarts: 7,
            competitionPodiums: 4,
            competitionWins: 3,
            exhibitionStarts: 5,
            exhibitionPodiums: 2,
            exhibitionWins: 1,
            agilityWins: 1,
            noseworkWins: 1,
            canicrossWins: 1,
            conformationWins: 0,
            progenyStarts: 2,
            progenyWins: 1,
            weeklyEventWins: 2,
            monthlyEventWins: 1,
            titlesCount: 4,
            titledDogsCount: 2,
            eventPrizeCoins: 175,
            eventFeesCoins: 125,
            littersStarted: 3,
            littersBorn: 2,
            puppiesBorn: 8,
            puppiesKept: 1,
            puppiesPurchased: 3,
            puppiesSold: 4,
            puppySalesCoins: 250,
            titledOffspring: 2,
            ammunitionPurchases: 6,
        },
        dogsCount: 2,
        exhibitionWins: 1,
        competitionWins: 3,
        walksCount: 8,
        trainingsCount: 4,
        joinedAt: '2026-10-01',
    };
}

async function renderStatistics(locale: string) {
    const player = playerFixture();
    const panel = defineComponent({
        setup: () => () => h(PlayerStatistics, { player }),
    });
    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'PlayerProfile',
                        url: '/players/player',
                        version: 'test',
                        rescuedProps: [],
                        flash: {},
                        rememberedState: {},
                        props: { locale, errors: {}, player },
                    },
                }),
        }),
    );
}

it.each(['en', 'ru'])(
    'keeps old activity statistics and adds recorded event, breeding and economy totals in %s',
    async (locale) => {
        const html = await renderStatistics(locale);
        const labels =
            locale === 'ru'
                ? {
                      feeding: 'Кормлений',
                      starts: 'Участия в соревнованиях',
                      breeding: 'Рождённые щенки',
                      titles: 'Полученные титулы',
                      monthly: 'Чемпионства месяца',
                      prize: 'Призовые за выступления',
                      currency: '175 монет',
                  }
                : {
                      feeding: 'Feedings',
                      starts: 'Competition starts',
                      breeding: 'Puppies born',
                      titles: 'Titles earned',
                      monthly: 'Monthly event championships',
                      prize: 'Event prize money earned',
                      currency: '175 coins',
                  };

        for (const [label, value] of [
            [labels.feeding, '12'],
            [labels.starts, '7'],
            [labels.breeding, '8'],
            [labels.titles, '4'],
            [labels.monthly, '1'],
            [labels.prize, labels.currency],
        ]) {
            expect(html).toContain(`<dt>${label}</dt><dd>${value}</dd>`);
        }
        expect(html).toContain('datetime="2026-10-04T12:00:00Z"');
        expect(html).not.toContain('NaN');
    },
);
