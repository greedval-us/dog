import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import Inventory from './Inventory.vue';
import type { InventoryItem } from '@/types/inventory';
import type { StatusEffect } from '@/types/pet-care';

function effectFixture(): StatusEffect {
    return {
        code: 'comfort',
        kind: 'buff',
        name: { en: 'Comfort' },
        description: { en: 'Helps your dog relax.' },
        modifiers: { mood_gain_percent: 10 },
        duration_seconds: 600,
        condition_state: null,
    };
}

async function renderInventory(attributes: Partial<InventoryItem>) {
    const item: InventoryItem = {
        id: 1,
        name: 'Toy',
        category: 'Toys',
        categoryCode: 'toys',
        quality: 3,
        usageLimit: 5,
        remainingUses: 5,
        characteristics: {},
        bonuses: {},
        grantedEffects: [],
        risks: [],
        acquiredAt: null,
        ...attributes,
    };
    const panel = defineComponent({
        setup: () => () =>
            h(Inventory, {
                categories: [],
                items: [item],
                selectedCategory: null,
                inventoryCount: 1,
                itemTypesCount: 1,
                nextCursor: null,
                previousCursor: null,
            }),
    });

    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'Inventory',
                        url: '/inventory',
                        version: 'test',
                        rescuedProps: [],
                        flash: {},
                        rememberedState: {},
                        props: {
                            locale: 'en',
                            errors: {},
                            auth: { user: { username: 'player' } },
                        },
                    },
                }),
        }),
    );
}

it.each([
    {
        detail: 'care bonuses',
        attributes: () => ({ bonuses: { mood: 5 } }),
        expected: 'Bonus per use',
    },
    {
        detail: 'granted effects',
        attributes: () => ({ grantedEffects: [effectFixture()] }),
        expected: 'Comfort',
    },
    {
        detail: 'risk warnings',
        attributes: () => ({
            risks: [
                {
                    effect: { ...effectFixture(), kind: 'debuff' as const },
                    chance: 500,
                    item_name: { en: 'Toy' },
                    quality: 3,
                },
            ],
        }),
        expected: 'Chance per action: Comfort — 5%',
    },
])(
    'shows $detail when generic item characteristics are empty',
    async ({ attributes, expected }) => {
        const html = await renderInventory(attributes());

        expect(html).toContain(expected);
    },
);
