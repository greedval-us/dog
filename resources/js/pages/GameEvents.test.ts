import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import GameEvents from './GameEvents.vue';
import type { GameEventSummary } from '@/types/game-event';

it.each(['en', 'ru'])(
    'offers a qualification instead of advertising a podium payout for odor tests in %s',
    async (locale) => {
        const event: GameEventSummary = {
            id: 7,
            discipline: 'nosework',
            frequency: 'daily',
            status: 'scheduled',
            opensAt: '2026-10-03T00:00:00Z',
            closesAt: '2026-10-04T18:00:00Z',
            startsAt: '2026-10-04T18:15:00Z',
            fee: 25,
            prizes: [100, 60, 40],
            entryCount: 0,
            calculationVersion: 3,
            fieldSize: 8,
            humanCount: 0,
            clubCount: 0,
            participationRules: {
                playerDailyLimit: 3,
                petDailyLimit: 2,
                petRestHours: 2,
            },
            canRegister: true,
            stages: [],
            regulation: {
                version: 2,
                system: 'rkf',
                rank: 'qualifying',
                rankLabel: 'Qualification trial',
                country: 'RU',
                region: 'moscow',
                judgeId: 'judge-1',
                judgeName: 'Judge One',
                format: 'odor_test',
            },
        };
        const panel = defineComponent({
            setup: () => () => h(GameEvents, { events: [event] }),
        });

        const html = await renderToString(
            createSSRApp({
                render: () =>
                    h(App, {
                        initialComponent: panel as DefineComponent,
                        initialPage: {
                            component: 'GameEvents',
                            url: '/game-events',
                            version: 'test',
                            rescuedProps: [],
                            flash: {},
                            rememberedState: {},
                            props: { locale, errors: {} },
                        },
                    }),
            }),
        );

        expect(html).toContain(
            locale === 'ru' ? 'Квалификация' : 'Qualification',
        );
        expect(html).not.toContain(
            locale === 'ru' ? 'Главный приз' : 'First prize',
        );
        expect(html).not.toContain(locale === 'ru' ? '100 монет' : '100 coins');
    },
);
