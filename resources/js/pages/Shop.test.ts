import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it, vi } from 'vite-plus/test';
import Shop from './Shop.vue';
import type { ShopOffer } from '@/types/shop';

vi.mock('@/components/CategoryTabs.vue', () => ({
    default: defineComponent({
        emits: ['start'],
        setup(_, { emit }) {
            emit('start');
            return () => h('nav');
        },
    }),
}));

it('blocks checkout for the previous offer when a category visit starts', async () => {
    const offer: ShopOffer = {
        id: 1,
        itemId: 1,
        name: 'Food',
        description: '',
        category: 'Food',
        categoryCode: 'food',
        quality: 3,
        usageLimit: 5,
        bonuses: {},
        grantedEffects: [],
        risks: [],
        characteristics: {},
        currency: 'coins',
        price: 100,
        stock: 4,
        owned: 0,
    };
    const panel = defineComponent({
        setup: () => () =>
            h(Shop, {
                categories: [{ id: 1, code: 'food', name: 'Food' }],
                offers: [offer],
                selectedCategory: null,
                purchaseToken: 'purchase-token',
                inventoryCount: 0,
                nextCursor: null,
                previousCursor: null,
            }),
    });

    const html = await renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'Shop',
                        url: '/shop',
                        version: 'test',
                        rescuedProps: [],
                        flash: {},
                        rememberedState: {},
                        props: {
                            locale: 'en',
                            errors: {},
                            auth: { user: { username: 'player', coins: 200 } },
                        },
                    },
                }),
        }),
    );

    expect(html).toContain('Loading…');
    expect(html).toMatch(/<button[^>]*type="submit"[^>]*disabled/);
    expect(html).not.toContain('Purchasing…');
});
