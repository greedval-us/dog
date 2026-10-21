import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import BoardingSettings from './BoardingSettings.vue';
import type { BoardingPlan } from '@/types/player';

async function renderSettings(plan: BoardingPlan, locale = 'en') {
    const panel = defineComponent({
        setup: () => () =>
            h(BoardingSettings, {
                boarding: {
                    plan,
                    inactivitySeconds: 3600,
                    hourlyPrices: { cheap: 2, premium: 6 },
                },
            }),
    });
    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'settings/Profile',
                        url: '/settings/profile',
                        version: 'test',
                        rescuedProps: [],
                        flash: {},
                        rememberedState: {},
                        props: { locale, errors: {} },
                    },
                }),
        }),
    );
}

it.each(['off', 'cheap', 'premium'] as const)(
    'submits the stored %s plan through its settings route',
    async (plan) => {
        const html = await renderSettings(plan);

        expect(html).toContain('action="/settings/boarding?_method=PUT"');
        expect(html).toContain('name="plan"');
        expect(html).toContain(`value="${plan}" selected`);
    },
);

it.each([
    ['en', '2 coins per dog per hour', '6 coins per dog per hour', 'after 1 h'],
    ['ru', '2 монет за собаку в час', '6 монет за собаку в час', 'через 1 ч'],
])(
    'explains the actual hourly prices and automatic start in %s',
    async (locale, cheap, premium, inactivity) => {
        const html = await renderSettings('cheap', locale);

        expect(html).toContain(cheap);
        expect(html).toContain(premium);
        expect(html).toContain(inactivity);
    },
);
