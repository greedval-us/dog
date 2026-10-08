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
        exterior: { type: 70, structure: 75, movement: 80 },
        titles: [],
        offspring: [],
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
    dog = dogFixture(),
    serverNow = '2026-10-04T12:00:00Z',
    equipment: EventEquipment[] = [],
): Promise<string> {
    const props = { event, entry, dogs: [dog], equipment, serverNow };
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
