import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import Achievements from './Achievements.vue';
import type { PlayerAchievement } from '@/types/achievement';
import type { PlayerProfile } from '@/types/player';

function playerFixture(): PlayerProfile {
    return {
        name: 'Preview player',
        username: 'preview',
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
            feedingCount: 0,
            wateringCount: 0,
            playCount: 0,
            groomingCount: 0,
            restCount: 0,
            skillLessonsCount: 0,
            workCount: 0,
            veterinaryCount: 0,
            activeDays: 1,
            lastActionAt: null,
        },
        dogsCount: 1,
        exhibitionWins: 0,
        competitionWins: 0,
        walksCount: 0,
        trainingsCount: 50,
        joinedAt: '2026-10-01',
    };
}

function achievementFixture(): PlayerAchievement {
    return {
        id: 1,
        code: 'training-100',
        name: 'Training veteran',
        description: 'The sofa has started to miss you.',
        imageUrl: '/images/achievements/training-100.svg',
        rule: 'Complete 100 training sessions.',
        progress: 50,
        target: 100,
        unlockedAt: null,
    };
}

async function renderAchievements(
    locale: string,
    achievements: PlayerAchievement[],
    player: PlayerProfile = playerFixture(),
) {
    const props = {
        player,
        achievements,
        unlockedCount: achievements.filter(
            (achievement) => achievement.unlockedAt,
        ).length,
        totalCount: achievements.length,
    };
    const panel = defineComponent({
        setup: () => () => h(Achievements, props),
    });

    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'players/Achievements',
                        url: '/players/preview/achievements',
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
    ['en', 'Not unlocked yet', 'Unlocked on', 'give no rewards or bonuses'],
    ['ru', 'Ещё не получено', 'Получено', 'не дают наград и бонусов'],
])(
    'shows unlocked dates and remaining progress in %s',
    async (locale, lockedLabel, unlockedLabel, rewardsText) => {
        const locked = achievementFixture();
        const unlocked = {
            ...achievementFixture(),
            id: 2,
            code: 'training-50',
            progress: 50,
            target: 50,
            unlockedAt: '2026-10-03T12:00:00Z',
        };

        const html = await renderAchievements(locale, [locked, unlocked]);

        expect(html).toContain(lockedLabel);
        expect(html).toContain(unlockedLabel);
        expect(html).toContain(rewardsText);
        expect(html).toContain('value="50" max="100"');
        expect(html).toContain('value="50" max="50"');
        expect(html).toContain('href="/players/preview"');
        expect(html).toContain(locked.rule);
    },
);

it('escapes player names and achievement content', async () => {
    const player = playerFixture();
    const achievement = achievementFixture();
    const unsafeText = '<script>alert(1)</script>';
    player.username = unsafeText;
    achievement.name = unsafeText;
    achievement.description = unsafeText;
    achievement.rule = unsafeText;

    const html = await renderAchievements('en', [achievement], player);

    expect(html).not.toContain(unsafeText);
    expect(html).toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('explains an empty achievement catalogue', async () => {
    const html = await renderAchievements('en', []);

    expect(html).toContain('Achievements will appear here soon.');
});
