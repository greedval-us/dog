import { App, router } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { afterEach, expect, it, vi } from 'vite-plus/test';
import Shop from './Shop.vue';
import type { ShopOffer } from '@/types/shop';

const panelEvents = vi.hoisted(() => ({
    buy: () => {},
    refresh: () => {},
}));

vi.mock('@inertiajs/vue3', async () => {
    const inertia =
        await vi.importActual<typeof import('@inertiajs/vue3')>(
            '@inertiajs/vue3',
        );
    return {
        ...inertia,
        useForm: (data: { purchase_token: string }) => {
            const form = inertia.useForm(data);
            form.setError('purchase_token', 'The offer changed.');
            return form;
        },
    };
});

vi.mock('@/components/ShopPurchasePanel.vue', async () => {
    const { default: PurchasePanel } = await vi.importActual<
        typeof import('@/components/ShopPurchasePanel.vue')
    >('@/components/ShopPurchasePanel.vue');
    return {
        default: defineComponent({
            props: ['offer', 'processing', 'loading', 'purchased', 'error'],
            emits: ['buy', 'refresh'],
            setup(props, { emit }) {
                panelEvents.buy = () => emit('buy');
                panelEvents.refresh = () => emit('refresh');
                return () =>
                    h(PurchasePanel, {
                        ...props,
                        onBuy: panelEvents.buy,
                        onRefresh: panelEvents.refresh,
                    });
            },
        }),
    };
});

async function renderShop() {
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
    const shopProps = {
        categories: [],
        offers: [offer],
        selectedCategory: null,
        purchaseToken: 'purchase-token',
        inventoryCount: 0,
        nextCursor: null,
        previousCursor: null,
    };
    const panel = defineComponent({
        setup: () => () => h(Shop, shopProps),
    });
    const app = createSSRApp({
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
                        ...shopProps,
                    },
                },
            }),
    });

    return renderToString(app);
}

afterEach(() => {
    vi.restoreAllMocks();
});

it('blocks checkout and duplicate refreshes until recovery from a purchase error finishes', async () => {
    const purchase = vi.spyOn(router, 'post').mockImplementation(() => {});
    let reloadOptions: Parameters<typeof router.reload>[0];
    const reload = vi.spyOn(router, 'reload').mockImplementation((options) => {
        reloadOptions = options;
        options?.onStart?.({} as never);
    });
    const html = await renderShop();

    expect(html).toContain('Refresh shop');
    panelEvents.refresh();
    panelEvents.refresh();
    panelEvents.buy();

    expect(reload).toHaveBeenCalledTimes(1);
    expect(purchase).not.toHaveBeenCalled();

    reloadOptions?.onFinish?.({} as never);
    panelEvents.buy();

    expect(purchase).toHaveBeenCalledTimes(1);
});
