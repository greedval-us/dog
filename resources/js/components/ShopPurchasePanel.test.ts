import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
import ShopPurchasePanel from './ShopPurchasePanel.vue';
import type { ShopOffer } from '@/types/shop';

async function renderPurchase(coins: number, stock: number | null = null) {
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
        stock,
        owned: 0,
    };
    const purchasePanel = defineComponent({
        inheritAttrs: false,
        setup: () => () =>
            h(ShopPurchasePanel, {
                offer,
                processing: false,
                purchased: false,
                error: '',
            }),
    });
    const app = createSSRApp({
        render: () =>
            h(App, {
                initialComponent: purchasePanel as DefineComponent,
                initialPage: {
                    component: 'ShopPurchasePanel',
                    url: '/shop',
                    version: 'test',
                    rescuedProps: [],
                    flash: {},
                    rememberedState: {},
                    props: {
                        locale: 'en',
                        errors: {},
                        auth: { user: { username: 'player', coins } },
                        offer,
                        processing: false,
                        purchased: false,
                        error: '',
                    },
                },
            }),
    });

    return renderToString(app);
}

describe('shop purchase feedback', () => {
    it('explains the exact shortfall and offers a way to earn coins', async () => {
        const html = await renderPurchase(70);

        expect(html).toContain('You need 30 more coins.');
        expect(html).toContain('Earn coins at daily work');
        expect(html).toMatch(/<button[^>]*type="submit"[^>]*disabled/);
        expect(html).toMatch(/aria-describedby="[^"]+-purchase-reason"/);
    });

    it('allows a purchase when the balance exactly matches the price', async () => {
        const html = await renderPurchase(100);

        expect(html).not.toMatch(/<button[^>]*type="submit"[^>]*disabled/);
        expect(html).not.toContain('You need');
    });

    it.each([70, 200])(
        'explains depleted stock with a balance of %i coins',
        async (coins) => {
            const html = await renderPurchase(coins, 0);

            expect(html).toContain(
                'This item is out of stock. Choose another item.',
            );
            expect(html).toMatch(/<button[^>]*type="submit"[^>]*disabled/);
            expect(html).not.toContain('Earn coins at daily work');
        },
    );
});
