import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { Component, DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import PetPedigreePage from './PetPedigree.vue';
import PetProfilePage from './PetProfile.vue';
import type {
    PedigreeNode,
    PetPedigree,
    PetPublicProfile,
} from '@/types/pet-pedigree';

function profileFixture(): PetPublicProfile {
    return {
        id: 1,
        name: 'Rey',
        breed: 'German Shepherd',
        sex: 'male',
        size: 'large',
        coatColor: 'Black and tan',
        generation: 4,
        description: 'A calm, observant dog.',
        bornAt: '2026-09-04T00:00:00Z',
        isPurebred: true,
        traits: ['loyal'],
        stats: {
            endurance: { value: 52, potential: 112 },
            speed: { value: 51, potential: 103 },
            strength: { value: 56, potential: 106 },
            agility: { value: 53, potential: 108 },
            obedience: { value: 55, potential: 114 },
            intelligence: { value: 54, potential: 117 },
        },
        lifecycle: { status: 'active', archivedAt: null },
        hasPedigree: true,
    };
}

function nodeFixture(
    id: number,
    name: string,
    sex: 'male' | 'female',
): PedigreeNode {
    return {
        pet: {
            id,
            name,
            breed: 'German Shepherd',
            sex,
            coatColor: 'Black and tan',
            generation: 1,
            hasPedigree: false,
            status: 'active',
        },
        father: null,
        mother: null,
    };
}

function pedigreeFixture(): PetPedigree {
    const root = nodeFixture(1, 'Rey', 'male');
    root.pet.generation = 3;
    root.pet.hasPedigree = true;
    root.father = nodeFixture(2, 'Atlas', 'male');
    root.mother = nodeFixture(3, 'Luna', 'female');
    root.father.pet.hasPedigree = true;
    root.father.father = nodeFixture(4, 'Boris', 'male');
    root.father.mother = nodeFixture(5, 'Ada', 'female');
    return { root, hasAncestors: true, generations: 3 };
}

async function renderPage(
    component: Component,
    props: Record<string, unknown>,
    locale = 'en',
    url = '/pets/1',
): Promise<string> {
    const panel = defineComponent({ setup: () => () => h(component, props) });
    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'preview',
                        url,
                        version: 'test',
                        rescuedProps: [],
                        flash: {},
                        rememberedState: {},
                        props: { locale, errors: {}, ...props },
                    },
                }),
        }),
    );
}

it.each([
    ['en', 'Father: Atlas. View dog card', 'Mother: Luna. View dog card'],
    [
        'ru',
        'Отец: Atlas. Открыть карточку собаки',
        'Мать: Luna. Открыть карточку собаки',
    ],
])(
    'connects known ancestors to their read-only cards in %s',
    async (locale, fatherLabel, motherLabel) => {
        const html = await renderPage(
            PetPedigreePage,
            { pedigree: pedigreeFixture() },
            locale,
        );

        expect(html).toContain(`aria-label="${fatherLabel}"`);
        expect(html).toContain(`aria-label="${motherLabel}"`);
        expect(html).toContain('href="/pets/2?from_pedigree=1"');
        expect(html).toContain('href="/pets/4?from_pedigree=1"');
        expect(html).toContain('tabindex="0"');
        expect(html).toContain('id="pedigree-generations"');
        expect(html).toContain('value="1"');
        expect(html).toContain('value="2"');
        expect(html).toContain('value="3"');
    },
);

it('preserves a missing parent as an unknown branch without creating a dog link', async () => {
    const pedigree = pedigreeFixture();
    pedigree.root.mother = null;

    const html = await renderPage(PetPedigreePage, { pedigree });

    expect(html).toContain('Unknown parent');
    expect(html).toContain('No record in the pedigree');
    expect(html).not.toContain('href="/pets/3?');
});

it('stops the displayed tree at its generation limit and explains how to continue', async () => {
    const pedigree = pedigreeFixture();
    pedigree.generations = 1;

    const html = await renderPage(PetPedigreePage, { pedigree });

    expect(html).toContain('href="/pets/2?from_pedigree=1"');
    expect(html).not.toContain('href="/pets/4?');
    expect(html).toContain('open an ancestor’s card and follow their pedigree');
});

it('explains an absent lineage without drawing invented ancestors', async () => {
    const pedigree = pedigreeFixture();
    pedigree.root.father = null;
    pedigree.root.mother = null;
    pedigree.hasAncestors = false;

    const html = await renderPage(PetPedigreePage, { pedigree }, 'ru');

    expect(html).toContain('Известных предков пока нет');
    expect(html).not.toContain('class="pedigree-tree"');
    expect(html).not.toContain('href="/pets/2?');
});

it.each([
    ['active', null, 'Main attributes'],
    ['retired', '2026-10-01T00:00:00Z', 'Retired'],
    ['deceased', '2026-10-01T00:00:00Z', 'In loving memory'],
] as const)(
    'shows the %s dog’s identity and attributes without gameplay controls',
    async (status, archivedAt, label) => {
        const profile = profileFixture();
        profile.lifecycle = { status, archivedAt };

        const html = await renderPage(PetProfilePage, { profile });

        expect(html).toContain('A calm, observant dog.');
        expect(html).toContain('Black and tan');
        expect(html).toContain(label);
        expect(html).toContain('52 / 112');
        expect(html).toContain('54 / 117');
        expect(html).toContain('href="/pets/1/pedigree"');
        expect(html).not.toContain('<form');
        expect(html).not.toContain('Train');
        expect(html).not.toContain('pet-care');
    },
);

it('escapes user-entered names and descriptions and omits the pedigree action without ancestors', async () => {
    const profile = profileFixture();
    profile.name = '<script>alert(1)</script>';
    profile.description = '<img src=x onerror=alert(1)>';
    profile.hasPedigree = false;

    const html = await renderPage(PetProfilePage, { profile }, 'ru');

    expect(html).toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    expect(html).toContain('&lt;img src=x onerror=alert(1)&gt;');
    expect(html).not.toContain('<script>alert');
    expect(html).not.toContain('href="/pets/1/pedigree"');
    expect(html).toContain('Известных предков пока нет');
});

it.each(['9', '2147483648', '9007199254740991'])(
    'returns an ancestor card to originating family %s while linking to its own pedigree',
    async (originId) => {
        const html = await renderPage(
            PetProfilePage,
            { profile: profileFixture() },
            'en',
            `/pets/1?from_pedigree=${originId}`,
        );

        expect(html).toContain('Back to pedigree');
        expect(html).toContain(`href="/pets/${originId}/pedigree"`);
        expect(html).toContain('href="/pets/1/pedigree"');
    },
);

it.each([
    '-1',
    '0',
    '1.2',
    'https://other.example',
    '9007199254740992',
    '1000000000000000000',
])('ignores an invalid originating pedigree %s', async (value) => {
    const html = await renderPage(
        PetProfilePage,
        { profile: profileFixture() },
        'en',
        `/pets/1?from_pedigree=${encodeURIComponent(value)}`,
    );

    expect(html).toContain('Back to my dog');
    expect(html).not.toContain('Back to pedigree');
});
