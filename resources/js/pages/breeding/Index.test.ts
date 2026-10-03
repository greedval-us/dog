import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { Component, DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import Breeding from './Index.vue';
import PuppyMarket from '../puppies/Market.vue';
import MyPuppies from '../puppies/Index.vue';
import type { BreedingParent, Puppy } from '@/types/breeding';

function parentFixture(): BreedingParent {
    return {
        id: 1,
        name: 'Milo',
        sex: 'female',
        breed: 'pitbull',
        breedName: 'Pit bull',
        coatColor: 'black',
        coatColorLabel: 'Black',
        generation: 1,
        reason: null,
        cooldownUntil: null,
        stats: {
            endurance: { value: 90, potential: 100 },
            speed: { value: 90, potential: 100 },
            strength: { value: 90, potential: 100 },
            agility: { value: 90, potential: 100 },
            obedience: { value: 90, potential: 100 },
            intelligence: { value: 90, potential: 100 },
        },
    };
}
function puppyFixture(): Puppy {
    return {
        id: 9,
        name: 'Puppy',
        breed: 'Pit bull',
        breedCode: 'pitbull',
        illustration: null,
        sex: 'male',
        coatColor: 'blue',
        coatLabel: 'Blue',
        generation: 2,
        potentials: {
            endurance: 105,
            speed: 101,
            strength: 108,
            agility: 102,
            obedience: 106,
            intelligence: 107,
        },
        status: 'listed',
        price: 50,
        expiresAt: '2026-10-11T12:00:00Z',
        seller: { name: 'Other player', username: 'other' },
    };
}
async function renderPage(
    component: Component,
    props: Record<string, unknown>,
    locale = 'en',
    coins = 0,
): Promise<string> {
    const panel = defineComponent({ setup: () => () => h(component, props) });
    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'preview',
                        url: '/breeding',
                        version: 'test',
                        rescuedProps: [],
                        flash: {},
                        rememberedState: {},
                        props: {
                            locale,
                            errors: {},
                            auth: { user: { username: 'preview', coins } },
                            ...props,
                        },
                    },
                }),
        }),
    );
}
function breedingFixture() {
    const mother = parentFixture();
    const father = {
        ...parentFixture(),
        id: 2,
        name: 'Leo',
        sex: 'male' as const,
    };
    return {
        access: { level: 5, requiredLevel: 5, allowed: true },
        dogs: [mother],
        ownListings: [],
        listings: [
            { id: 1, price: 100, owner: { username: 'other' }, pet: father },
        ],
        partners: [],
        selection: { petId: 1, kind: 'listing', partnerId: 1 },
        preview: {
            father,
            mother,
            ranges: [{ stat: 'endurance', min: 103, max: 110 }],
            colors: [
                { code: 'blue', label: 'Blue', chance: 2, rare: true },
                { code: 'black', label: 'Black', chance: 98, rare: false },
            ],
            reason: null,
        },
        operationToken: '00000000-0000-4000-8000-000000000001',
    };
}

it.each([
    ['en', 'Breeding opens at level 5'],
    ['ru', 'Разведение открывается на 5 уровне'],
])('explains the level gate in %s', async (locale, message) => {
    const props = breedingFixture();
    props.access = { level: 1, requiredLevel: 5, allowed: false };

    const html = await renderPage(Breeding, props, locale);

    expect(html).toContain(message);
    expect(html).not.toContain('id="breeding-dog"');
});

it('shows genetic ranges and ancestry-aware coat chances before spending coins', async () => {
    const html = await renderPage(Breeding, breedingFixture());

    expect(html).toContain('103–110');
    expect(html).toContain('2%');
    expect(html).toContain('Rare');
    expect(html).toContain('known ancestors across three generations');
    expect(html).toContain('You need 100 more coins.');
});

it('only offers publication for male dogs and retains withdrawal for existing offers', async () => {
    const props = {
        ...breedingFixture(),
        ownListings: [{ id: 9, petId: 2, price: 100, isActive: true }],
    };
    props.dogs.push({ ...parentFixture(), id: 2, name: 'Leo', sex: 'male' });

    const femaleHtml = await renderPage(Breeding, props);
    const maleHtml = await renderPage(Breeding, {
        ...props,
        selection: { petId: 2, kind: null, partnerId: null },
        preview: null,
    });

    expect(femaleHtml).toContain(
        'Select a male dog to publish a breeding offer.',
    );
    expect(femaleHtml).not.toContain('id="breeding-price"');
    expect(femaleHtml).not.toContain('Publish or update offer');
    expect(femaleHtml).toContain('Withdraw offer');
    expect(maleHtml).toContain('id="breeding-price"');
    expect(maleHtml).toContain('Publish or update offer');
});

it('preserves the selected pair while moving between breeding offer pages', async () => {
    const html = await renderPage(Breeding, {
        ...breedingFixture(),
        listingPagination: { previousCursor: null, nextCursor: 'next-offers' },
    });

    expect(html).toContain('aria-label="Breeding offer pages"');
    expect(html).toContain('pet=1');
    expect(html).toContain('kind=listing');
    expect(html).toContain('partner=1');
    expect(html).toContain('cursor=next-offers');
});

it('allows an own-parent pairing with zero coins despite a quoted listing fee', async () => {
    const html = await renderPage(Breeding, {
        ...breedingFixture(),
        ownPair: true,
        selectedPrice: 0,
    });
    const beginPosition = html.indexOf('Begin breeding');
    const button = html.slice(
        html.lastIndexOf('<button', beginPosition),
        beginPosition,
    );

    expect(html).toContain(
        'Both parents belong to you. No breeding fee is charged.',
    );
    expect(html).not.toContain('You need 100 more coins.');
    expect(button).not.toContain('disabled');
});

it('blocks buying one’s own puppy and links to its management page', async () => {
    const puppy = puppyFixture();
    puppy.seller = { name: 'Player', username: 'preview' };

    const html = await renderPage(
        PuppyMarket,
        {
            puppies: [puppy],
            source: 'players',
            freeSlots: 1,
            blocked: false,
            kennelPrice: 500,
            pagination: { previousCursor: null, nextCursor: null },
        },
        'en',
        1000,
    );

    expect(html).toContain('This puppy already belongs to you.');
    expect(html).toContain('href="/puppies"');
    expect(html).toMatch(/<button[^>]*disabled/);
});

it('shows the exact purchase shortfall and omits deadlines for kennel puppies', async () => {
    const puppy = puppyFixture();
    puppy.status = 'kennel';
    puppy.price = null;
    puppy.seller = null;

    const html = await renderPage(
        PuppyMarket,
        {
            puppies: [puppy],
            source: 'kennel',
            freeSlots: 1,
            blocked: false,
            kennelPrice: 500,
            pagination: { previousCursor: null, nextCursor: null },
        },
        'en',
        300,
    );

    expect(html).toContain('You need 200 more coins.');
    expect(html).not.toContain('Decision deadline');
    expect(html).toContain('Waiting for a home at the kennel');
});

it('preserves a parent filter in pagination and blocks keeping puppies with no free slot', async () => {
    const html = await renderPage(MyPuppies, {
        puppies: [puppyFixture()],
        pregnancies: [],
        freeSlots: 0,
        blocked: false,
        parentId: 4,
        pagination: { previousCursor: null, nextCursor: 'next-page' },
    });

    expect(html).toContain('parent=4');
    expect(html).toContain('A free dog slot is needed to keep a puppy.');
    expect(html).toMatch(/<button[^>]*disabled/);
});
