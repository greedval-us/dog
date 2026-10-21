import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import EventRegulationSummary from './EventRegulationSummary.vue';
import type { EventRegulation } from '@/types/game-event';

async function renderRegulation(
    locale: string,
    regulation: EventRegulation,
    compact = false,
) {
    const panel = defineComponent({
        setup: () => () => h(EventRegulationSummary, { regulation, compact }),
    });
    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'GameEventShow',
                        url: '/game-events/1',
                        version: 'test',
                        flash: {},
                        rescuedProps: [],
                        rememberedState: {},
                        props: { locale, errors: {} },
                    },
                }),
        }),
    );
}

it.each(['ru', 'en'])(
    'shows the special show edition, localized subject and every safe source in %s',
    async (locale) => {
        const regulation: EventRegulation = {
            version: 2,
            system: 'rkf',
            rank: 'cacib',
            rankLabel: 'CACIB',
            country: 'RU',
            region: 'novosibirsk',
            regionLabel:
                locale === 'ru'
                    ? 'Новосибирская область'
                    : 'Novosibirsk Oblast',
            judgeId: 'judge-1',
            judgeName: 'Judge One',
            format: 'conformation',
            specialShowLabel: locale === 'ru' ? 'Евразия' : 'Eurasia',
            showLegLabel: 'RKF Cup',
            showYear: 2026,
            showEdition: '2026-10',
            sources: [
                { url: 'https://example.test/show', label: 'Show rules' },
                { url: 'https://example.test/titles', label: 'Title rules' },
                { url: 'javascript:alert(1)', label: 'Unsafe' },
            ],
            policyNote: 'Only first place earns this certificate.',
        };

        const html = await renderRegulation(locale, regulation);

        expect(html).toContain(regulation.specialShowLabel);
        expect(html).toContain(regulation.regionLabel);
        expect(html).toContain(
            locale === 'ru' ? 'Игровой год: 2026' : 'Game year: 2026',
        );
        expect(html).toContain(
            locale === 'ru'
                ? 'Игровой выпуск: 2026-10'
                : 'Game edition: 2026-10',
        );
        expect(html).toContain('href="https://example.test/show"');
        expect(html).toContain('href="https://example.test/titles"');
        expect(html).toContain('Only first place earns this certificate.');
        expect(html).not.toContain('javascript:');
        const compactHtml = await renderRegulation(locale, regulation, true);
        expect(compactHtml).toContain(regulation.specialShowLabel);
        expect(compactHtml).not.toContain('href="https://example.test/show"');
    },
);
