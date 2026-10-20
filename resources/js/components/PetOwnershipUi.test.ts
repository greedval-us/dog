import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { Component, DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import Kennel from '@/pages/Kennel.vue';
import PetMarketPurchase from '@/components/PetMarketPurchase.vue';
import PetSaleForm from '@/components/PetSaleForm.vue';

async function renderOwnershipComponent(
    component: Component,
    props: Record<string, unknown>,
    coins = 500,
    locale = 'en',
) {
    const panel = defineComponent({ setup: () => () => h(component, props) });
    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'Kennel',
                        url: '/kennel',
                        version: 'test',
                        rescuedProps: [],
                        flash: {},
                        rememberedState: {},
                        props: {
                            locale,
                            errors: {},
                            auth: {
                                user: { id: 1, username: 'player', coins },
                            },
                        },
                    },
                }),
        }),
    );
}

it.each([true, false])(
    'shows an explicit age choice for free=%s kennel dogs',
    async (free) => {
        const html = await renderOwnershipComponent(Kennel, {
            breeds: [
                {
                    id: 1,
                    name: 'Shepherd',
                    description: '',
                    size: 'large',
                    illustration: null,
                    potentials: {
                        endurance: 100,
                        speed: 100,
                        strength: 100,
                        agility: 100,
                        obedience: 100,
                        intelligence: 100,
                    },
                },
            ],
            canClaimStarterPet: free,
            freeSlots: 1,
            price: 500,
            adoptionToken: 'token',
            ageOptions: [
                {
                    months: 3,
                    label: 'Puppy, 3 months',
                    description: 'Early classes',
                },
                {
                    months: 18,
                    label: 'Young dog, 18 months',
                    description: 'Sport now',
                },
            ],
        });

        expect(html).toContain('name="age_months"');
        expect(html).toContain('value="3"');
        expect(html).toContain('value="18"');
        expect(html).toContain('Early classes');
        expect(html).toContain('Sport now');
    },
);

const listing = {
    id: 7,
    price: 137,
    seller: { id: 2, username: 'seller' },
    pet: {
        id: 11,
        name: 'Veteran',
        breed: 'Shepherd',
        sex: 'male',
        bornAt: '2026-06-22',
        ageMonths: 120,
        retired: true,
        titles: [],
    },
};

it('shows a pensioners age and offers care without presenting a sports career', async () => {
    const html = await renderOwnershipComponent(PetMarketPurchase, {
        listing,
        freeSlots: 1,
        purchaseToken: 'token',
    });
    expect(html).toContain('Age: 10 years');
    expect(html).toContain('Retired');
    expect(html).toContain('Buy this dog');
    expect(html).toContain('href="/pets/11"');
});

it.each([
    [0, 500, 'You need a free dog slot.'],
    [1, 100, 'You need 37 more coins.'],
])(
    'explains why a market dog cannot be bought with %s slots and %s coins',
    async (freeSlots, coins, message) => {
        const html = await renderOwnershipComponent(
            PetMarketPurchase,
            { listing, freeSlots, purchaseToken: 'token' },
            coins,
        );
        expect(html).toContain(message);
        expect(html.replace(/<!--[\s\S]*?-->/g, '')).toMatch(
            /<button[^>]*disabled[^>]*>Buy this dog/,
        );
    },
);

it('submits the listed price and exposes withdrawal for an existing listing', async () => {
    const html = await renderOwnershipComponent(PetSaleForm, {
        petId: 11,
        ownership: { listing: { id: 7, price: 137 }, reason: null },
    });
    expect(html).toContain('action="/pets/11/sale"');
    expect(html).toContain('name="price"');
    expect(html).toContain('value="137"');
    expect(html).toContain('action="/pets/11/sale?_method=DELETE"');
    expect(html).toContain('Withdraw listing');
});
