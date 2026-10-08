import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import PlayerProfilePage from './PlayerProfile.vue';
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
            actionsCount: 0,
            feedingCount: 0,
            wateringCount: 0,
            playCount: 0,
            groomingCount: 0,
            restCount: 0,
            skillLessonsCount: 0,
            workCount: 0,
            veterinaryCount: 0,
            activeDays: 0,
            lastActionAt: null,
            competitionStarts: 0,
            competitionPodiums: 0,
            competitionWins: 0,
            exhibitionStarts: 0,
            exhibitionPodiums: 0,
            exhibitionWins: 0,
            agilityWins: 0,
            noseworkWins: 0,
            canicrossWins: 0,
            conformationWins: 0,
            progenyStarts: 0,
            progenyWins: 0,
            weeklyEventWins: 0,
            monthlyEventWins: 0,
            titlesCount: 0,
            titledDogsCount: 0,
            eventPrizeCoins: 0,
            eventFeesCoins: 0,
            littersStarted: 0,
            littersBorn: 0,
            puppiesBorn: 0,
            puppiesKept: 0,
            puppiesPurchased: 0,
            puppiesSold: 0,
            puppySalesCoins: 0,
            titledOffspring: 0,
            ammunitionPurchases: 0,
        },
        dogsCount: 1,
        exhibitionWins: 0,
        competitionWins: 0,
        walksCount: 0,
        trainingsCount: 0,
        joinedAt: null,
    };
}

async function renderProfile(
    isOwner: boolean,
    locale: string,
): Promise<string> {
    const props = {
        player: playerFixture(),
        isOwner,
        inventoryCount: isOwner ? 0 : null,
        dogs: [
            {
                id: 17,
                name: 'Rey',
                breed: 'Shepherd',
                portraitId: null,
                backgroundId: null,
            },
        ],
        dailyWork: null,
    };
    const panel = defineComponent({
        setup: () => () => h(PlayerProfilePage, props),
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
                        props: { locale, errors: {}, ...props },
                    },
                }),
        }),
    );
}

it.each([
    ['en', 'View dog card'],
    ['ru', 'Посмотреть карточку'],
])('lets a visitor open a dog’s public card in %s', async (locale, label) => {
    const html = await renderProfile(false, locale);

    expect(html).toContain('href="/pets/17"');
    expect(html).toContain(label);
    expect(html).not.toContain('href="/dashboard?pet=17"');
});

it.each([
    ['en', 'Open dog'],
    ['ru', 'Открыть'],
])('keeps the owner’s dog-management link in %s', async (locale, label) => {
    const html = await renderProfile(true, locale);

    expect(html).toContain('href="/dashboard?pet=17"');
    expect(html).toContain(label);
    expect(html).not.toContain('href="/pets/17"');
});
