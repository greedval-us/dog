import { App } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import type { DefineComponent } from 'vue';
import { expect, it, vi } from 'vite-plus/test';
import GameEventShow from './GameEventShow.vue';
import type {
    EventDog,
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
        canRegister: true,
        stages: ['approach', 'technical', 'finish'].map((key) => ({
            key,
            label: key,
            options: ['careful', 'balanced', 'bold'],
        })),
        entries: [],
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
): Promise<string> {
    const props = { event, entry, dogs: [dog], equipment: [] };
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
    expect(html).toContain('Final score: 235');
    expect(html).not.toContain('type="radio"');
    expect(html).not.toContain('Competition equipment');
    expect(html).not.toContain('Highlight standout type');
    expect(html).not.toContain('<dt>Time</dt>');
    expect(html).not.toContain('<dt>Fatigue</dt>');
    expect(html).not.toContain('<dt>Focus</dt>');
});
