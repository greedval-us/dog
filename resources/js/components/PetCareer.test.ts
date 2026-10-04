import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import PetCareer from './PetCareer.vue';
import type { PetCareer as PetCareerData } from '@/types/pet-career';

function careerFixture(): PetCareerData {
    return {
        petId: 7,
        summary: {
            competitionStarts: 3,
            competitionWins: 2,
            exhibitionStarts: 1,
            exhibitionWins: 0,
            podiums: 2,
            cups: 1,
        },
        titles: [
            {
                name: 'Agility champion',
                discipline: 'agility',
                frequency: 'weekly',
                count: 2,
                awardedAt: '2026-10-04T12:00:00Z',
                eventId: 42,
            },
        ],
        results: {
            entries: [
                {
                    id: 5,
                    eventId: 42,
                    kind: 'competition',
                    discipline: 'agility',
                    frequency: 'weekly',
                    division: 'large',
                    divisionLabel: 'Large dogs',
                    rank: 1,
                    prize: 175,
                    completedAt: '2026-10-04T12:00:00Z',
                    petName: 'Rey',
                    eliminated: false,
                },
                {
                    id: 3,
                    eventId: 40,
                    kind: 'competition',
                    discipline: 'nosework',
                    frequency: 'daily',
                    division: 'large',
                    divisionLabel: 'Large dogs',
                    rank: 1,
                    prize: 0,
                    completedAt: '2026-10-03T12:00:00Z',
                    petName: 'Rey',
                    eliminated: true,
                },
            ],
            nextCursor: 'older',
            previousCursor: null,
        },
    };
}

async function renderCareer(locale: string, career: PetCareerData) {
    const panel = defineComponent({
        setup: () => () => h(PetCareer, { petId: 7, career }),
    });
    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'Dashboard',
                        url: '/dashboard?pet=7',
                        version: 'test',
                        flash: {},
                        rescuedProps: [],
                        rememberedState: {},
                        props: { locale, errors: {}, career },
                    },
                }),
        }),
    );
}

it.each(['en', 'ru'])(
    'shows actual grouped awards and linked results, without a winner’s place for elimination in %s',
    async (locale) => {
        const html = await renderCareer(locale, careerFixture());

        expect(html).toContain('Agility champion');
        expect(html).toContain(
            locale === 'ru' ? 'Награждений: 2' : 'Awarded 2 times',
        );
        expect(html).toContain('href="/game-events/42"');
        expect(html).toContain('href="/game-events/40"');
        expect(html).toContain('Large dogs');
        expect(html).toContain(locale === 'ru' ? '175 монет' : '175 coins');
        expect(html).toContain(
            locale === 'ru'
                ? '<dt>Кубки еженедельных событий</dt><dd>1</dd>'
                : '<dt>Weekly event cups</dt><dd>1</dd>',
        );
        expect(html).toContain(
            locale === 'ru' ? 'Снятие с дистанции' : 'Eliminated',
        );
        expect(html.match(/class="pet-career-place"/g)).toHaveLength(2);
        expect(html.match(/is-winner/g)).toHaveLength(1);
        expect(html).toContain(
            locale === 'ru' ? 'Ранние записи' : 'Older entries',
        );
    },
);

it.each(['en', 'ru'])(
    'explains a first career and links to registration in %s',
    async (locale) => {
        const career = careerFixture();
        career.titles = [];
        career.results.entries = [];
        career.results.nextCursor = null;
        const html = await renderCareer(locale, career);

        expect(html).toContain(
            locale === 'ru'
                ? 'Здесь начинается карьера собаки'
                : 'Your dog’s career starts here',
        );
        expect(html).toContain('href="/game-events"');
        expect(html).not.toContain('pet-career-result-list');
    },
);
