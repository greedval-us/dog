import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it, vi } from 'vite-plus/test';
import GameEventShow from './GameEventShow.vue';
import type {
    EventDog,
    EventEquipment,
    GameEventDetail,
    GameEventEntry,
    GameEventEntryProps,
} from '@/types/game-event';

function eventFixture(): GameEventDetail {
    return {
        id: 7,
        discipline: 'agility',
        frequency: 'daily',
        status: 'scheduled',
        opensAt: '2026-10-03T00:00:00Z',
        closesAt: '2026-10-04T18:00:00Z',
        startsAt: '2026-10-04T18:30:00Z',
        fee: 25,
        prizes: [70, 40, 25],
        entryCount: 0,
        calculationVersion: 2,
        fieldSize: 8,
        humanCount: 0,
        clubCount: 0,
        participationRules: {
            playerDailyLimit: 3,
            petDailyLimit: 2,
            petRestHours: 2,
        },
        canRegister: true,
        stages: ['approach', 'technical', 'finish'].map((key) => ({
            key,
            label: key,
            options: ['careful', 'balanced', 'bold'],
        })),
        entries: [],
        divisions: [],
        activeDivision: null,
    };
}
function dogFixture(): EventDog {
    return {
        id: 1,
        name: 'Rey',
        breed: 'Shepherd',
        size: 'large',
        isActive: true,
        isBusy: false,
        ageMonths: 18,
        availableClasses: [],
        defaultClass: null,
        exterior: { type: 70, structure: 75, movement: 80 },
        titles: [],
        offspring: [],
    };
}

function preparationFixture(): NonNullable<EventDog['preparation']> {
    return {
        careMultiplier: 1,
        initialFocus: 70,
        initialFatigue: 0,
        states: { health: 100, energy: 100, hydration: 100, satiety: 100 },
        normalizedStats: { speed: 60, agility: 50 },
        stats: { speed: 105, agility: 70 },
        potentials: { speed: 220, agility: 200 },
        skills: {},
        modifiers: { precision: 0, focus: 0, stamina: 0, pace: 0 },
        stages: [],
        pedigree: { generation: 1, knownParents: 0 },
        divisionLabel: 'Agility 1 · large',
        blockingReasons: [],
    };
}

function regulationFixture() {
    return {
        version: 1,
        system: 'rkf' as const,
        rank: 'cac',
        rankLabel: 'National CAC show',
        country: 'RU',
        region: 'Moscow',
        judgeId: 'judge-1',
        judgeName: 'Judge One',
        format: 'conformation',
    };
}
function entryFixture(): GameEventEntry {
    return {
        id: 4,
        petId: 1,
        name: 'Rey',
        ownerName: 'rey-owner',
        isNpc: false,
        division: 'novice:large',
        status: 'registered',
        plan: { stages: ['careful', 'balanced', 'bold'] },
        gearIds: [],
        rank: null,
        prize: 0,
        result: null,
    };
}
async function renderEvent(
    event: GameEventDetail,
    entry: GameEventEntry | null,
    locale = 'en',
    dog: EventDog | EventDog[] = dogFixture(),
    serverNow = '2026-10-04T12:00:00Z',
    equipment: EventEquipment[] = [],
    selection: Pick<
        GameEventEntryProps,
        'selectedPetId' | 'selectedEntryOptionKey'
    > = {},
): Promise<string> {
    const props = {
        event,
        entry,
        dogs: Array.isArray(dog) ? dog : [dog],
        equipment,
        serverNow,
        ...selection,
    };
    const panel = defineComponent({
        setup: () => () => h(GameEventShow, props),
    });
    return renderToString(
        createSSRApp({
            render: () =>
                h(App, {
                    initialComponent: panel as DefineComponent,
                    initialPage: {
                        component: 'GameEventShow',
                        url: '/game-events/7',
                        version: 'test',
                        rescuedProps: [],
                        flash: {},
                        rememberedState: {},
                        props: {
                            locale,
                            errors: {},
                            auth: {
                                user: {
                                    id: 2,
                                    username: 'rey-owner',
                                    coins: 500,
                                },
                            },
                            ...props,
                        },
                    },
                }),
        }),
    );
}

it('opens a career recommendation with its dog and class selected', async () => {
    const event = eventFixture();
    event.discipline = 'conformation';
    event.regulation = regulationFixture();
    const firstDog = dogFixture();
    firstDog.entryOptions = [
        { key: 'open', label: 'Open', plan: { exhibition_class: 'open' } },
    ];
    const recommendedDog = {
        ...dogFixture(),
        id: 2,
        name: 'Luna',
        entryOptions: [
            { key: 'open', label: 'Open', plan: { exhibition_class: 'open' } },
            {
                key: 'working',
                label: 'Working',
                plan: { exhibition_class: 'working' },
            },
        ],
    };

    const html = await renderEvent(
        event,
        null,
        'en',
        [firstDog, recommendedDog],
        undefined,
        [],
        { selectedPetId: 2, selectedEntryOptionKey: 'working' },
    );

    expect(html).toMatch(/<option(?=[^>]*value="2")(?=[^>]*selected)[^>]*>/);
    expect(html).toMatch(
        /<option(?=[^>]*value="working")(?=[^>]*selected)[^>]*>/,
    );
});

it.each(['en', 'ru'])(
    'explains that odor recognition tests award a qualification without podium prizes in %s',
    async (locale) => {
        const event = eventFixture();
        event.discipline = 'nosework';
        event.regulation = { ...regulationFixture(), format: 'odor_test' };

        const html = await renderEvent(event, null, locale);

        expect(html).toContain(
            locale === 'ru'
                ? 'Тест распознавания запаха даёт квалификацию без призового места и денежной награды.'
                : 'Odor tests record a qualification without a placement or coin prize.',
        );
        expect(html).not.toContain('class="event-prizes"');
        expect(html).toContain(
            locale === 'ru' ? 'Заявок пока нет.' : 'No entries yet.',
        );
        expect(html).toContain(
            locale === 'ru'
                ? 'Собаку оценивают отдельно по выбранному запаху.'
                : 'Each dog is assessed individually on the selected odor.',
        );
        expect(html).not.toContain(
            locale === 'ru'
                ? 'В каждом классе и категории — единый зачёт.'
                : 'Each class and category has one shared standing.',
        );
        expect(html).not.toContain(
            locale === 'ru'
                ? 'Собаки клуба занимают свободные места'
                : 'Club dogs fill empty places',
        );
        expect(html).not.toContain(
            locale === 'ru'
                ? 'Перед стартом свободные места займут собаки клуба.'
                : 'Club dogs will join before the start',
        );
    },
);

it.each(['en', 'ru'])(
    'retains shared standings and club participation for nosework searches in %s',
    async (locale) => {
        const event = eventFixture();
        event.discipline = 'nosework';
        event.regulation = { ...regulationFixture(), format: 'nosework' };

        const html = await renderEvent(event, null, locale);

        expect(html).toContain(
            locale === 'ru'
                ? 'В каждом классе и категории — единый зачёт.'
                : 'Each class and category has one shared standing.',
        );
        expect(html).toContain(
            locale === 'ru'
                ? 'Собаки клуба занимают свободные места'
                : 'Club dogs fill empty places',
        );
        expect(html).toContain(
            locale === 'ru'
                ? 'Перед стартом свободные места займут собаки клуба.'
                : 'Club dogs will join before the start',
        );
        expect(html).toContain('class="event-prizes"');
    },
);

it.each(['en', 'ru'])(
    'shows the regulation, derived height and eligible performance choices in %s',
    async (locale) => {
        const event = eventFixture();
        event.regulation = {
            ...regulationFixture(),
            format: 'jumping',
            formatLabel: locale === 'ru' ? 'Джампинг' : 'Jumping',
            rankLabel: 'National agility trial',
        };
        const dog = dogFixture();
        dog.profile = { heightCm: 46, heightCategory: 'I' };
        dog.entryOptions = [
            {
                key: 'agility:1',
                label: 'Agility 1 · I',
                categoryLabel: 'Intermediate · 43–48 cm',
                plan: { sport_class: 'a1', test_type: 'agility' },
            },
            {
                key: 'agility:3',
                label: 'Agility 3 · I',
                plan: { sport_class: 'a3', test_type: 'agility' },
                eligible: false,
                reason: 'Earn the previous qualification first.',
            },
        ];
        dog.defaultEntryOption = 'agility:1';

        const html = await renderEvent(event, null, locale, dog);

        expect(html).toContain('National agility trial');
        expect(html).toContain(locale === 'ru' ? 'Джампинг' : 'Jumping');
        expect(html).toContain('Judge One');
        expect(html).toContain('Agility 1 · I');
        expect(html).toMatch(
            /<option(?=[^>]*value="agility:3")(?=[^>]*disabled)[^>]*>/,
        );
        expect(html).toContain('Earn the previous qualification first.');
        expect(html).toContain(
            locale === 'ru'
                ? 'Рост в холке: 46 см'
                : 'Height at withers: 46 cm',
        );
    },
);

it.each(['en', 'ru'])(
    'separates qualification, category standings and certificates from a cash prize in %s',
    async (locale) => {
        const event = eventFixture();
        event.discipline = 'nosework';
        event.regulation = {
            ...regulationFixture(),
            format: 'nosework',
            rankLabel: 'Nosework qualification trial',
        };
        event.status = 'completed';
        event.canRegister = false;
        const entry = entryFixture();
        entry.status = 'completed';
        entry.rank = null;
        entry.prize = 0;
        entry.result = {
            time: 80,
            score: 235,
            penalties: 0,
            eliminated: false,
            stages: [],
            assessment: {
                code: 'not_qualified',
                label: 'Qualification not achieved',
                qualified: false,
            },
            standings: [
                {
                    key: 'objects',
                    label: 'Objects · Programme 1 · NW-1',
                    rank: 2,
                    score: 96,
                    time: 21,
                    penalties: 4,
                    qualified: true,
                },
            ],
        };
        entry.awards = [
            {
                name: 'NW-1 programme certificate',
                code: 'NW1',
                kind: 'qualification',
                discipline: 'nosework',
                frequency: 'daily',
                awardedAt: '2026-10-04T18:40:00Z',
                classKey: 'nw1',
            },
        ];
        event.entries = [entry];

        const html = await renderEvent(event, entry, locale);

        expect(html).toContain('Qualification not achieved');
        expect(html).toContain('Objects · Programme 1 · NW-1');
        expect(html).toContain('NW-1 programme certificate');
        expect(html).toContain(
            locale === 'ru' ? 'Квалификации' : 'Qualifications',
        );
        expect(html).not.toMatch(
            locale === 'ru' ? />\s*0 монет\s*</ : />\s*0 coins\s*</,
        );
        expect(html).not.toContain('Place 0');
    },
);

it.each([
    {
        deviceNow: '2099-01-01T00:00:00Z',
        serverNow: '2026-10-04T12:00:00Z',
        open: true,
    },
    {
        deviceNow: '1999-01-01T00:00:00Z',
        serverNow: '2026-10-04T18:00:00Z',
        open: false,
    },
])(
    'uses server registration time when the device clock reads $deviceNow',
    async ({ deviceNow, serverNow, open }) => {
        vi.spyOn(Date, 'now').mockReturnValue(Date.parse(deviceNow));
        try {
            const event = eventFixture();
            const entry = open ? null : entryFixture();
            const html = await renderEvent(
                event,
                entry,
                'en',
                dogFixture(),
                serverNow,
            );

            if (open) {
                expect(html).toContain('type="submit"');
            } else {
                expect(html).not.toContain('type="submit"');
                expect(html).toContain(
                    'Registration is closed. Saved plans are ready for the start.',
                );
            }
        } finally {
            vi.restoreAllMocks();
        }
    },
);

it.each(['en', 'ru'])(
    'uses the server participation limits and explains rest until preparation begins in %s',
    async (locale) => {
        const event = eventFixture();
        event.participationRules = {
            playerDailyLimit: 7,
            petDailyLimit: 5,
            petRestHours: 4,
        };

        const html = await renderEvent(event, null, locale);

        expect(html).toContain(
            locale === 'ru'
                ? 'до 7 участий в день по МСК'
                : 'up to 7 events per Moscow day',
        );
        expect(html).toContain(
            locale === 'ru'
                ? 'до 5 физических выступлений в день по МСК'
                : 'up to 5 physical competitions and exhibitions',
        );
        expect(html).toContain(
            locale === 'ru' ? 'должно пройти не менее 4 ч.' : 'at least 4 h',
        );
        expect(html).toContain(
            locale === 'ru'
                ? 'закрытием регистрации следующего, когда начинается подготовка'
                : 'registration closes, when preparation begins',
        );
    },
);

it.each(['en', 'ru'])(
    'exempts progeny from the physical dog limit and rest while retaining the player limit in %s',
    async (locale) => {
        const event = eventFixture();
        event.discipline = 'progeny';
        event.participationRules.playerDailyLimit = 7;

        const html = await renderEvent(event, null, locale);

        expect(html).toContain(
            locale === 'ru'
                ? 'до 7 участий в день по МСК'
                : 'up to 7 events per Moscow day',
        );
        expect(html).toContain(
            locale === 'ru'
                ? 'не учитывается в лимите физических выступлений собаки и не требует отдыха'
                : 'does not count toward the dog’s physical event limit and does not require event rest',
        );
        expect(html).not.toContain(
            locale === 'ru' ? 'должно пройти не менее' : 'Allow at least',
        );
        expect(html).not.toContain(
            locale === 'ru' ? 'У собаки — до' : 'A dog may enter up to',
        );
    },
);

it('keeps a withdrawn entry as a refunded receipt without offering registration again', async () => {
    vi.setSystemTime(new Date('2026-10-04T12:00:00Z'));
    try {
        const event = eventFixture();
        const entry = { ...entryFixture(), status: 'withdrawn' };
        event.canRegister = false;
        event.entries = [entry];

        const html = await renderEvent(event, entry);

        expect(html).toContain(
            'Your entry was withdrawn and the fee refunded.',
        );
        expect(html).not.toContain('type="submit"');
        expect(html).not.toContain('Withdraw and refund entry fee');
    } finally {
        vi.useRealTimers();
    }
});

it('shows server preparation and blockers while allowing a busy dog to be selected', async () => {
    const dog = dogFixture();
    dog.isBusy = true;
    dog.preparation = {
        careMultiplier: 0.86,
        initialFocus: 62,
        initialFatigue: 24,
        states: {
            health: 58,
            energy: 70,
            hydration: 65,
            satiety: 80,
            mood: 85,
            bond: 90,
        },
        normalizedStats: { speed: 60, agility: 50 },
        stats: { speed: 105, agility: 70 },
        potentials: { speed: 220, agility: 200 },
        skills: {},
        modifiers: { precision: 0, focus: 0, stamina: 0, pace: 0 },
        stages: [
            {
                key: 'approach',
                quality: 56,
                weights: { speed: 0.6, agility: 0.4 },
            },
        ],
        pedigree: { generation: 2, knownParents: 2 },
        divisionLabel: 'Novice · large',
        blockingReasons: ['Health must be at least 60%.'],
    };

    const html = await renderEvent(eventFixture(), null, 'en', dog);

    expect(html).toContain('Health must be at least 60%.');
    expect(html).toMatch(
        /data-testid="preparation-division"[^>]*>Novice · large<\/span>/,
    );
    expect(html).toContain('Care effect on quality');
    expect(html).toContain('0.86');
    expect(html).toContain('Hydration');
    expect(html).toContain('Speed 60% · Agility 40%');
    expect(html).toContain('href="/dashboard?pet=1"');
    expect(html).toContain('href="/pets/1/pedigree"');
    expect(html).toMatch(
        /<option(?=[^>]*value="1")(?=[^>]*selected)(?![^>]*disabled)[^>]*>/,
    );
});

it.each([
    ['en', 'Discipline experience', 'Experience risk reduction', '8 pp'],
    ['ru', 'Опыт в дисциплине', 'Снижение риска за счёт опыта', '8 п. п.'],
])(
    'shows the server discipline experience and bounded risk reduction in %s',
    async (locale, experienceLabel, reductionLabel, reduction) => {
        const dog = dogFixture();
        dog.preparation = {
            ...preparationFixture(),
            careerExperience: 140,
            consistencyBonus: 0.08,
        };

        const html = await renderEvent(eventFixture(), null, locale, dog);

        expect(html).toMatch(
            new RegExp(`${experienceLabel}</dt>\\s*<dd>140</dd>`),
        );
        expect(html).toContain(reductionLabel);
        expect(html).toContain(reduction);
    },
);

it.each([
    ['en', 'Warm-up effect', 'Equipment is removed before the agility course.'],
    ['ru', 'Эффект разминки', 'Перед трассой аджилити амуницию снимают.'],
])(
    'keeps selected regulated agility equipment effects as warm-up effects in %s',
    async (locale, effectLabel, explanation) => {
        const event = eventFixture();
        event.regulation = { ...regulationFixture(), format: 'agility' };
        const entry = entryFixture();
        entry.gearIds = [10];
        const equipment: EventEquipment[] = [
            {
                id: 10,
                name: 'Warm-up collar',
                slot: 'neck',
                disciplines: ['agility'],
                phase: 'preparation',
                sizes: [],
                modifiers: { precision: 0.06, focus: 0.04 },
                remainingUses: 3,
            },
            {
                id: 11,
                name: 'Performance collar',
                slot: 'neck',
                disciplines: ['agility'],
                phase: 'performance',
                sizes: [],
                modifiers: { precision: 0.2 },
                remainingUses: 3,
            },
        ];

        const html = await renderEvent(
            event,
            entry,
            locale,
            dogFixture(),
            undefined,
            equipment,
        );

        expect(html).toContain(`aria-label="${effectLabel}"`);
        expect(html).toContain(explanation);
        expect(html).toContain('Warm-up collar');
        expect(html).not.toContain('Performance collar');
        expect(html).toContain(
            locale === 'ru'
                ? 'Базовый риск ошибки: -6 п. п.'
                : 'Base error risk: -6 pp',
        );
        expect(html).toContain(
            locale === 'ru' ? 'Концентрация: +4 ед.' : 'Focus: +4 points',
        );
    },
);

it('shows recorded experience and warm-up effects in a regulated agility replay', async () => {
    const event = eventFixture();
    event.status = 'completed';
    event.canRegister = false;
    event.regulation = { ...regulationFixture(), format: 'agility' };
    const entry = entryFixture();
    entry.result = {
        time: 24,
        score: -24,
        penalties: 0,
        eliminated: false,
        preparation: {
            ...preparationFixture(),
            careerExperience: 45,
            consistencyBonus: 0.045,
            modifiers: { precision: 0.06, focus: 0.04, stamina: 0, pace: 0 },
        },
        stages: [
            {
                key: 'approach',
                decision: 'careful',
                time: 24,
                score: -24,
                penalties: 0,
                fatigue: 0,
                focus: 70,
                reason: 'Clear stage.',
                factors: {
                    quality: 50,
                    careMultiplier: 1,
                    mistakeChance: 0.18,
                    startFatigue: 0,
                    startFocus: 70,
                    exterior: null,
                    exteriorContribution: 0,
                    presentationContribution: 0,
                    consistencyBonus: 0.045,
                },
            },
        ],
    };
    event.entries = [entry];
    const dog = dogFixture();
    dog.preparation = {
        ...preparationFixture(),
        careerExperience: 140,
        consistencyBonus: 0.08,
    };

    const html = await renderEvent(event, entry, 'en', dog);

    expect(html).toMatch(/Discipline experience<\/dt>\s*<dd>45<\/dd>/);
    expect(html.match(/4\.5 pp/g)).toHaveLength(2);
    expect(html).toContain('Recorded warm-up effect');
    expect(html).toContain('Base error risk: -6 pp');
    expect(html).not.toContain('<dd>140</dd>');
});

it('keeps documentary progeny evaluation free of experience risk claims', async () => {
    const event = eventFixture();
    event.discipline = 'progeny';
    const dog = dogFixture();
    dog.preparation = {
        ...preparationFixture(),
        careerExperience: 80,
        consistencyBonus: 0,
    };
    const liveHtml = await renderEvent(event, null, 'en', dog);
    event.status = 'completed';
    event.canRegister = false;
    const entry = entryFixture();
    entry.result = {
        time: 0,
        score: 80,
        penalties: 0,
        eliminated: false,
        preparation: dog.preparation,
        stages: [
            {
                key: 'type',
                decision: 'balanced',
                time: 0,
                score: 80,
                penalties: 0,
                fatigue: 0,
                focus: 100,
                reason: 'Documentary evaluation.',
                factors: {
                    quality: 80,
                    careMultiplier: 1,
                    mistakeChance: 0,
                    startFatigue: 0,
                    startFocus: 100,
                    exterior: null,
                    exteriorContribution: 0,
                    presentationContribution: 0,
                    consistencyBonus: 0,
                },
            },
        ],
    };
    event.entries = [entry];
    const recordedHtml = await renderEvent(event, entry, 'en', dog);

    for (const html of [liveHtml, recordedHtml]) {
        expect(html).not.toContain('Experience risk reduction');
        expect(html).not.toContain(
            'Experience in this discipline lowers the base error risk',
        );
        expect(html).not.toContain('Error risk');
    }
});

it('keeps recorded condition separate from current care in completed results', async () => {
    const event = eventFixture();
    event.status = 'completed';
    event.canRegister = false;
    const entry = entryFixture();
    entry.result = {
        time: 24,
        score: -24,
        penalties: 1,
        eliminated: false,
        preparation: {
            careMultiplier: 0.81,
            initialFocus: 42,
            initialFatigue: 30,
            states: { health: 70 },
            normalizedStats: { speed: 50 },
            modifiers: { precision: 0.1, focus: 0, stamina: 0, pace: 0 },
        },
        stages: [
            {
                key: 'approach',
                decision: 'careful',
                time: 24,
                score: -24,
                penalties: 1,
                fatigue: 38,
                focus: 43,
                reason: 'A mistake on the first stage.',
                factors: {
                    quality: 40.5,
                    careMultiplier: 0.81,
                    mistakeChance: 0.18,
                    startFatigue: 30,
                    startFocus: 42,
                    exterior: null,
                    exteriorContribution: 0,
                    presentationContribution: 0,
                },
            },
        ],
    };
    event.entries = [entry];

    const html = await renderEvent(event, entry);

    expect(html).toContain('Condition recorded when registration closed');
    expect(html).toContain('0.81');
    expect(html).toContain('18%');
    expect(html).toContain('40.5');
    expect(html).toContain('Recorded equipment effect');
    expect(html).toContain('Base error risk: -10 pp');
    expect(html).not.toContain('Discipline experience');
    expect(html).not.toContain('Experience risk reduction');
    expect(html).not.toContain('Preparation at a glance');
    expect(html.indexOf('Your result')).toBeLessThan(
        html.indexOf('Saved entry and rules'),
    );
});

it('links division filters while retaining the own result outside the selected group', async () => {
    const event = eventFixture();
    event.status = 'completed';
    event.activeDivision = 'novice:small:heat-2';
    event.divisions = [
        {
            key: 'novice:large',
            label: 'Large · group 1',
            humanCount: 5,
            clubCount: 3,
        },
        {
            key: 'novice:small:heat-2',
            label: 'Small · group 2',
            humanCount: 6,
            clubCount: 2,
        },
    ];
    const entry = entryFixture();
    entry.rank = 2;
    entry.result = {
        time: 42,
        score: -42,
        penalties: 0,
        eliminated: false,
        stages: [],
    };

    const html = await renderEvent(event, entry);

    expect(html).toContain('/game-events/7?division=novice%3Asmall%3Aheat-2');
    expect(html).toContain('6 players · 2 club dogs');
    expect(html).toContain('aria-current="page"');
    expect(html).toContain('Performance replay — Rey');
    expect(html).toMatch(
        /<h2(?=[^>]*data-slot="card-title")(?=[^>]*tabindex="-1")[^>]*>\s*Performance replay — Rey\s*<\/h2>/,
    );
    expect(html).toContain('Place 2');
});

it('does not count preparation-only equipment toward the required canicross kit', async () => {
    const event = eventFixture();
    event.discipline = 'canicross';
    const entry = entryFixture();
    entry.gearIds = [10, 11, 12];
    const equipment: EventEquipment[] = ['body', 'line', 'handler'].map(
        (slot, index) => ({
            id: 10 + index,
            name: `Invalid ${slot}`,
            slot,
            disciplines: ['canicross'],
            phase: 'preparation',
            sizes: [],
            modifiers: {},
            remainingUses: 3,
        }),
    );

    const html = await renderEvent(
        event,
        entry,
        'en',
        dogFixture(),
        '2026-10-04T12:00:00Z',
        equipment,
    );

    expect(html).not.toContain('Invalid body');
    expect(html).toContain('Select all three before entering.');
    expect(html).toMatch(
        /<button(?=[^>]*type="submit")(?=[^>]*disabled)[^>]*>/,
    );
});

it('shows the server-localized automatic withdrawal reason without claiming a performance took place', async () => {
    const event = eventFixture();
    event.status = 'completed';
    event.canRegister = false;
    const reason = 'Собака заболела перед стартом. Взнос возвращён.';
    const entry = entryFixture();
    entry.status = 'withdrawn';
    entry.result = {
        time: 0,
        penalties: 0,
        score: 0,
        eliminated: true,
        reason,
        stages: [],
    };
    event.entries = [entry];

    const html = await renderEvent(event, entry, 'ru');

    expect(html).toContain('Заявка снята, взнос возвращён.');
    expect(html).toContain('Заявка снята — Rey');
    expect(html).toContain('aria-label="Причина снятия: Rey"');
    expect(html.split(reason)).toHaveLength(3);
    expect(html).not.toContain('Выступление завершено.');
    expect(html).not.toContain('event-replay-stages');
    expect(html).not.toContain('type="submit"');
});

it('explains the mandatory canicross kit and disables entry when the kit is incomplete', async () => {
    vi.setSystemTime(new Date('2026-10-04T12:00:00Z'));
    try {
        const event = eventFixture();
        event.discipline = 'canicross';
        event.stages = ['climb', 'turns', 'finish'].map((key) => ({
            key,
            label: key,
            options: ['careful', 'balanced', 'bold'],
        }));

        const html = await renderEvent(event, null);

        expect(html).toContain(
            'Canicross requires a pulling harness, tugline and handler belt.',
        );
        expect(html).toMatch(
            /<button(?=[^>]*type="submit")(?=[^>]*disabled)[^>]*>/,
        );
    } finally {
        vi.useRealTimers();
    }
});

it('shows saved tactics and every result stage while marking club dogs as NPCs', async () => {
    const event = eventFixture();
    event.status = 'completed';
    event.canRegister = false;
    const entry = entryFixture();
    entry.status = 'completed';
    entry.rank = 1;
    entry.prize = 70;
    entry.result = {
        time: 33,
        penalties: 5,
        score: 80,
        eliminated: false,
        stages: [
            {
                key: 'approach',
                decision: 'careful',
                time: 13,
                penalties: 0,
                score: 30,
                fatigue: 5,
                focus: 90,
                reason: 'Steady start without errors.',
            },
            {
                key: 'technical',
                decision: 'balanced',
                time: 12,
                penalties: 5,
                score: 20,
                fatigue: 12,
                focus: 74,
                reason: 'Missed a contact zone.',
            },
            {
                key: 'finish',
                decision: 'bold',
                time: 8,
                penalties: 0,
                score: 30,
                fatigue: 22,
                focus: 67,
                reason: 'Used the remaining reserve.',
            },
        ],
    };
    event.entries = [
        entry,
        {
            ...entryFixture(),
            id: 9,
            petId: 999,
            name: 'Club Luna',
            ownerName: null,
            isNpc: true,
            rank: 2,
        },
    ];

    const html = await renderEvent(event, entry);

    expect(html).toContain('Performance replay — Rey');
    expect(html).toContain('Missed a contact zone.');
    expect(html).toContain('aria-label="View replay: Rey"');
    expect(html).toContain('Used the remaining reserve.');
    expect(html).toContain('Final acceleration');
    expect(html).toContain('33 s · 5 penalties');
    expect(html).toContain('Club dog (NPC)');
    expect(html).not.toContain('href="/pets/999"');
});

it('evaluates a retired producer through the selected offspring without offering tactics or equipment effects', async () => {
    const event = eventFixture();
    event.discipline = 'progeny';
    event.status = 'completed';
    event.canRegister = false;
    event.stages = ['type', 'uniformity', 'achievements'].map((key) => ({
        key,
        label: key,
        options: ['careful', 'balanced', 'bold'],
    }));
    const dog = dogFixture();
    dog.isActive = false;
    dog.offspring = [2, 3, 4].map((id) => ({
        id,
        name: `Offspring ${id}`,
        exterior: { type: 85, structure: 80, movement: 77 },
        titlesCount: 2,
    }));
    const entry = entryFixture();
    entry.status = 'completed';
    entry.plan = { stages: ['bold', 'bold', 'bold'], offspring_ids: [2, 3, 4] };
    entry.result = {
        time: 0,
        penalties: 0,
        score: 235,
        eliminated: false,
        stages: event.stages.map((stage, index) => ({
            key: stage.key,
            decision: 'bold',
            time: 0,
            penalties: 0,
            score: [85, 100, 50][index],
            fatigue: 0,
            focus: 100,
            reason: 'Evaluated the selected offspring.',
        })),
    };
    event.entries = [entry];

    const html = await renderEvent(event, entry, 'en', dog);

    expect(html).toContain('Judging criteria');
    expect(html).toContain('Offspring 2');
    expect(html).toContain('Progeny evaluation — Rey');
    expect(html).toContain('aria-label="View evaluation: Rey"');
    expect(html).toContain('Final score: 235');
    expect(html).not.toContain('type="radio"');
    expect(html).not.toContain('Competition equipment');
    expect(html).not.toContain('Highlight standout type');
    expect(html).not.toContain('<dt>Time</dt>');
    expect(html).not.toContain('<dt>Fatigue</dt>');
    expect(html).not.toContain('<dt>Focus</dt>');
});

it('offers the eligible age classes and shows age at the exhibition start', async () => {
    const event = eventFixture();
    event.discipline = 'conformation';
    const dog = dogFixture();
    dog.ageMonths = 16;
    dog.defaultClass = 'junior';
    dog.availableClasses = [
        { key: 'junior', label: 'Junior' },
        { key: 'intermediate', label: 'Intermediate' },
        { key: 'open', label: 'Open' },
    ];
    const html = await renderEvent(event, null, 'en', dog);

    expect(html).toContain('id="event-exhibition-class"');
    expect(html).toContain('Intermediate');
    expect(html).toContain('Age at the start: 1 year 4 months');
    expect(html).toContain('One class per exhibition.');
});

it('shows all awards earned by one exhibition entry', async () => {
    const event = eventFixture();
    event.discipline = 'conformation';
    event.status = 'completed';
    event.canRegister = false;
    const entry = entryFixture();
    entry.status = 'completed';
    entry.rank = 1;
    entry.awards = [
        {
            name: '1st place — Open',
            discipline: 'conformation',
            frequency: 'daily',
            awardedAt: event.startsAt,
            code: 'conformation_class_place_1',
            classKey: 'open',
            rank: 1,
            ageMonths: 30,
            breedId: 1,
        },
        {
            name: 'Best male',
            discipline: 'conformation',
            frequency: 'daily',
            awardedAt: event.startsAt,
            code: 'conformation_best_male',
            classKey: 'open',
            rank: 1,
            ageMonths: 30,
            breedId: 1,
        },
        {
            name: 'Best of breed',
            discipline: 'conformation',
            frequency: 'daily',
            awardedAt: event.startsAt,
            code: 'conformation_best_of_breed',
            classKey: 'open',
            rank: 1,
            ageMonths: 30,
            breedId: 1,
        },
    ];
    event.entries = [entry];
    const html = await renderEvent(event, entry);

    expect(html).toContain('1st place — Open');
    expect(html).toContain('Best male');
    expect(html).toContain('Best of breed');
});

it('does not offer an archived producer as an eligible dog for a new progeny entry', async () => {
    const event = eventFixture();
    event.discipline = 'progeny';
    const dog = dogFixture();
    dog.isActive = false;
    dog.offspring = [2, 3, 4].map((id) => ({
        id,
        name: `Offspring ${id}`,
        exterior: dog.exterior,
        titlesCount: 0,
    }));
    const html = await renderEvent(event, null, 'en', dog);

    expect(html).toMatch(/<option(?=[^>]*value="1")(?=[^>]*disabled)[^>]*>/);
    expect(html).not.toContain('id="event-offspring-');
    expect(html).toMatch(
        /<button(?=[^>]*type="submit")(?=[^>]*disabled)[^>]*>/,
    );
});

it('shows the saved exhibition class in preparation instead of the server default class', async () => {
    const event = eventFixture();
    event.discipline = 'conformation';
    const dog = dogFixture();
    dog.ageMonths = 16;
    dog.defaultClass = 'junior';
    dog.availableClasses = [
        { key: 'junior', label: 'Junior' },
        { key: 'intermediate', label: 'Intermediate' },
        { key: 'open', label: 'Open' },
    ];
    dog.preparation = {
        careMultiplier: 1,
        initialFatigue: 0,
        initialFocus: 100,
        states: {},
        normalizedStats: {},
        modifiers: { precision: 0, stamina: 0, pace: 0, focus: 0 },
        stats: {},
        potentials: {},
        skills: {},
        stages: [],
        pedigree: { generation: 1, knownParents: 0 },
        divisionLabel: 'Junior · Shepherd · Male',
        blockingReasons: [],
    };
    const entry = entryFixture();
    entry.plan.exhibition_class = 'intermediate';
    const html = await renderEvent(event, entry, 'en', dog);

    expect(html).toMatch(
        /data-testid="preparation-division"[^>]*>Intermediate<\/span>/,
    );
    expect(html).not.toContain('Junior · Shepherd · Male');
});
