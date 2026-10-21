import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it } from 'vite-plus/test';
import PetTitleProgress from './PetTitleProgress.vue';
import type {
    PetTitleGoal,
    PetTitleProgress as ProgressData,
} from '@/types/pet-title-progress';

function goalFixture(): PetTitleGoal {
    return {
        key: 'agility:regional:moscow',
        code: 'SPORT_REGIONAL_CH',
        name: 'Regional agility champion',
        discipline: 'agility',
        scopeLabel: 'Moscow',
        status: 'in_progress',
        repeatable: true,
        earnedCount: 1,
        summary: 'One more certificate under another judge is needed.',
        paths: [
            {
                key: 'regional',
                label: 'Three regional certificates',
                ready: false,
                recommended: true,
                requirements: [
                    {
                        key: 'certificates',
                        label: 'Regional certificates',
                        current: 2,
                        target: 3,
                        met: false,
                        note: null,
                    },
                    {
                        key: 'judges',
                        label: 'Different judges',
                        current: 1,
                        target: 2,
                        met: false,
                        note: 'Choose a different judge.',
                    },
                    {
                        key: 'region',
                        label: 'Same federal subject',
                        current: 1,
                        target: 1,
                        met: true,
                        note: null,
                    },
                ],
                credentials: [
                    {
                        id: 1,
                        name: 'REG_CACAg eligible',
                        code: 'REG_CACAg',
                        awardedAt: '2026-10-18T12:00:00Z',
                        eventId: 30,
                        status: 'counted',
                        reason: null,
                    },
                    {
                        id: 2,
                        name: 'REG_CACAg exchanged',
                        code: 'REG_CACAg',
                        awardedAt: '2026-10-17T12:00:00Z',
                        eventId: 29,
                        status: 'used',
                        reason: 'Used for an earlier regional diploma.',
                    },
                    {
                        id: 3,
                        name: 'REG_CACAg expired',
                        code: 'REG_CACAg',
                        awardedAt: '2026-08-17T12:00:00Z',
                        eventId: null,
                        status: 'expired',
                        reason: 'Outside the document validity period.',
                    },
                ],
                events: [
                    {
                        id: 42,
                        name: 'Moscow certificate event',
                        startsAt: '2026-10-21T12:00:00Z',
                        closesAt: '2026-10-21T11:45:00Z',
                        classLabel: 'Agility 3 · M',
                        contribution: 'REG_CACAg under Judge Two',
                        eligible: true,
                        reason: null,
                        registered: false,
                        petId: 7,
                        optionKey: 'agility:a3:M',
                    },
                    {
                        id: 43,
                        name: 'Future international event',
                        startsAt: '2026-10-22T12:00:00Z',
                        closesAt: '2026-10-22T11:45:00Z',
                        classLabel: 'Agility 3 · M',
                        contribution: 'CACIAG',
                        eligible: false,
                        reason: 'This dog is not yet old enough.',
                        registered: false,
                    },
                ],
                notes: ['Certificates are exchanged only once.'],
            },
        ],
    };
}

async function renderProgress(locale: string, goals: PetTitleGoal[]) {
    const progress: ProgressData = {
        generatedAt: '2026-10-20T12:00:00Z',
        goals,
    };
    const panel = defineComponent({
        setup: () => () => h(PetTitleProgress, { progress }),
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
                        props: { locale, errors: {} },
                    },
                }),
        }),
    );
}

it.each(['en', 'ru'])(
    'shows missing conditions, document status and honest event actions in %s',
    async (locale) => {
        const html = await renderProgress(locale, [goalFixture()]);

        expect(html).toContain(
            locale === 'ru' ? 'Прогресс титулов' : 'Title progress',
        );
        expect(html).toContain('Regional agility champion');
        expect(html).toContain(
            'One more certificate under another judge is needed.',
        );
        expect(html).toContain('Regional certificates');
        expect(html).toMatch(/2\s*\/\s*3/);
        expect(html).toContain('Choose a different judge.');
        expect(html).toContain(
            locale === 'ru' ? 'Зачтён в этой схеме' : 'Counted for this path',
        );
        expect(html).toContain(
            locale === 'ru' ? 'Уже обменян' : 'Already exchanged',
        );
        expect(html).toContain(
            locale === 'ru' ? 'Срок действия истёк' : 'Expired',
        );
        expect(html).toContain('Used for an earlier regional diploma.');
        expect(html).toContain(
            'href="/game-events/42?pet=7&amp;entry_option=agility%3Aa3%3AM"',
        );
        expect(html).toContain('href="/game-events/43"');
        expect(html).toContain('href="/game-events/30"');
        expect(html).toContain(
            locale === 'ru' ? 'Открыть событие' : 'Open event',
        );
        expect(html).toContain(
            locale === 'ru' ? 'Посмотреть условия' : 'View requirements',
        );
        expect(html).toContain('This dog is not yet old enough.');
        expect(html).not.toContain('role="progressbar"');
    },
);

it('selects the server recommended path and keeps other choices accessible', async () => {
    const goal = goalFixture();
    const alternate = {
        ...goal.paths[0],
        key: 'alternate',
        label: 'Recommended alternate route',
    };
    goal.paths[0] = {
        ...goal.paths[0],
        recommended: false,
        requirements: [
            {
                key: 'old',
                label: 'Not the selected requirement',
                current: 0,
                target: 1,
                met: false,
                note: null,
            },
        ],
    };
    goal.paths.push(alternate);

    const html = await renderProgress('en', [goal]);

    expect(html).toContain('Title route');
    expect(html).toContain('Recommended alternate route');
    expect(html).toContain('Regional certificates');
    expect(html).not.toContain('Not the selected requirement');
});

it.each(['en', 'ru'])(
    'distinguishes earned, ready and unavailable goals without a registration promise in %s',
    async (locale) => {
        const achieved = {
            ...goalFixture(),
            key: 'achieved',
            status: 'achieved' as const,
            repeatable: false,
            paths: [],
        };
        const ready = {
            ...goalFixture(),
            key: 'ready',
            status: 'ready' as const,
            paths: [],
        };
        const unavailable = {
            ...goalFixture(),
            key: 'unavailable',
            status: 'unavailable' as const,
            summary: 'The dog is retired.',
            paths: [{ ...goalFixture().paths[0], events: [] }],
        };

        const html = await renderProgress(locale, [
            achieved,
            ready,
            unavailable,
        ]);

        expect(html).toContain(
            locale === 'ru' ? 'Титул получен' : 'Title earned',
        );
        expect(html).toContain(
            locale === 'ru' ? 'Условия выполнены' : 'Requirements met',
        );
        expect(html).toContain(
            locale === 'ru' ? 'Сейчас недоступен' : 'Currently unavailable',
        );
        expect(html).toContain('The dog is retired.');
        expect(html).not.toContain(
            locale === 'ru' ? 'Открыть событие' : 'Open event',
        );
    },
);

it('preserves all server goals with a disclosure after the first three', async () => {
    const goals = Array.from({ length: 5 }, (_, index) => ({
        ...goalFixture(),
        key: `goal-${index}`,
        name: `Goal ${index + 1}`,
    }));
    const html = await renderProgress('en', goals);

    expect(html).toContain('Other title goals');
    expect(html).toContain('Goal 1');
    expect(html).toContain('Goal 5');
    expect(html.match(/<article /g)).toHaveLength(5);
});

it('does not suggest earning more documents when the selected route is already complete', async () => {
    const goal = goalFixture();
    goal.status = 'ready';
    goal.paths[0] = {
        ...goal.paths[0],
        ready: true,
        events: [],
        requirements: goal.paths[0].requirements.map((requirement) => ({
            ...requirement,
            current: requirement.target,
            met: true,
            note: null,
        })),
    };

    const html = await renderProgress('en', [goal]);

    expect(html).toContain('All conditions for this route are met.');
    expect(html).not.toContain('Where to earn the missing documents');
    expect(html).not.toContain('Suitable events are not currently available.');
});

it.each(['en', 'ru'])(
    'explains when no title route is available in %s',
    async (locale) => {
        const html = await renderProgress(locale, []);

        expect(html).toContain(
            locale === 'ru'
                ? 'Пока нет доступных титульных целей.'
                : 'No title goals are available yet.',
        );
        expect(html).toContain('href="/game-events"');
    },
);
