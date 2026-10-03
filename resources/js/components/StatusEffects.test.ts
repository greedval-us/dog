import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
import StatusEffects from './StatusEffects.vue';
import type { StatusEffect } from '@/types/pet-care';

async function renderEffect(locale: string, attributes: Partial<StatusEffect>) {
    const effect: StatusEffect = {
        code: 'test-effect',
        kind: 'debuff',
        name: { en: 'Disease', ru: 'Болезнь' },
        description: { en: 'Your dog is unwell.', ru: 'Собака нездорова.' },
        modifiers: { energy_cost_percent: 20 },
        duration_seconds: null,
        condition_state: null,
        ...attributes,
    };
    const panel = defineComponent({
        setup: () => () =>
            h(StatusEffects, {
                effects: [effect],
                serverNow: '2026-10-03T10:00:00Z',
            }),
    });
    const app = createSSRApp({
        render: () =>
            h(App, {
                initialComponent: panel as DefineComponent,
                initialPage: {
                    component: 'StatusEffects',
                    url: '/dashboard',
                    version: 'test',
                    rescuedProps: [],
                    flash: {},
                    rememberedState: {},
                    props: { locale, errors: {} },
                },
            }),
    });

    return renderToString(app);
}

describe('effect lifetime labels', () => {
    it.each(['en', 'ru'])(
        'labels a disease as lasting until treatment in %s',
        async (locale) => {
            const html = await renderEffect(locale, {
                disease_id: 1,
                expires_at: null,
            });

            expect(html).toContain(
                locale === 'ru' ? 'До лечения' : 'Until treatment',
            );
            expect(html).not.toContain(
                locale === 'ru'
                    ? 'Пока выполняется условие'
                    : 'While the condition is met',
            );
        },
    );

    it('keeps the countdown for temporary effects', async () => {
        const html = await renderEffect('en', {
            duration_seconds: 120,
            expires_at: 1791021720,
        });

        expect(html).toContain('2 min left');
        expect(html).not.toContain('Until treatment');
    });

    it('keeps the condition label for state effects', async () => {
        const html = await renderEffect('en', { condition_state: 'satiety' });

        expect(html).toContain('While the condition is met');
        expect(html).not.toContain('Until treatment');
    });
});
