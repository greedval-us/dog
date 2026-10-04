import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
import ShopPurchasePanel from './ShopPurchasePanel.vue';
import type { ShopOffer } from '@/types/shop';

async function renderPurchase(
    coins: number,
    stock: number | null = null,
    overrides: Partial<ShopOffer> = {},
) {
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
        ...overrides,
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
    it('explains the delivery purchase limit even when stock and coins remain', async () => {
        const html = await renderPurchase(200, 4, {
            purchaseLimit: 1,
            purchasedThisPeriod: 1,
        });

        expect(html).toContain(
            'You have reached the purchase limit for this delivery.',
        );
        expect(html).toMatch(/<button[^>]*type="submit"[^>]*disabled/);
    });

    it('shows ammunition trade-offs and the Moscow delivery time without rendering nested metadata as an item property', async () => {
        const competition = {
            slot: 'body',
            disciplines: ['canicross'],
            phase: 'performance',
            sizes: ['large'],
            modifiers: { stamina: 0.08, pace: -0.04 },
        };

        const html = await renderPurchase(200, 4, {
            competition,
            characteristics: { competition, material: 'Nylon' },
            nextRestockAt: '2026-10-05T03:00:00Z',
        });

        expect(html).toContain('+8%');
        expect(html).toContain('-4%');
        expect(html).toContain('Nylon');
        expect(html).toContain('06:00');
        expect(html).toContain('(Moscow time)');
        expect(html).not.toContain('[object Object]');
        expect(html).not.toContain('<dt>Quality</dt>');
    });
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
