import { App } from '@inertiajs/vue3';
import { createRenderer, defineComponent, h, nextTick, reactive } from 'vue';
import type { DefineComponent } from 'vue';
import { afterEach, expect, it } from 'vite-plus/test';
import { useGameEventEntry } from './useGameEventEntry';
import type {
    EventDiscipline,
    EventDog,
    GameEventEntryProps,
    GameEventPlan,
} from '@/types/game-event';

let cleanup: (() => void) | undefined;
afterEach(() => cleanup?.());

function dog(id: number): EventDog {
    return {
        id,
        name: `Dog ${id}`,
        breed: 'Shepherd',
        size: 'large',
        isActive: true,
        isBusy: false,
        ageMonths: id === 1 ? 16 : 100,
        defaultClass: id === 1 ? 'junior' : 'veteran',
        availableClasses:
            id === 1
                ? [
                      { key: 'junior', label: 'Junior' },
                      { key: 'intermediate', label: 'Intermediate' },
                      { key: 'open', label: 'Open' },
                  ]
                : [{ key: 'veteran', label: 'Veteran' }],
        exterior: { type: 75, structure: 75, movement: 75 },
        titles: [],
        offspring: [],
    };
}

function mountEntry(discipline: EventDiscipline = 'conformation') {
    const props = reactive<GameEventEntryProps>({
        event: {
            id: 1,
            discipline,
            frequency: 'daily',
            status: 'completed',
            startsAt: '2026-10-20T18:00:00Z',
            opensAt: '2026-10-19T18:00:00Z',
            closesAt: '2026-10-20T17:45:00Z',
            fee: 25,
            prizes: [100, 60, 40],
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
            canRegister: false,
            stages: [],
            entries: [],
            divisions: [],
            activeDivision: null,
        },
        serverNow: '2026-10-20T12:00:00Z',
        dogs: [dog(1), dog(2)],
        equipment: [],
        entry: null,
    });
    let state!: ReturnType<typeof useGameEventEntry>;
    const renderer = createRenderer<object, object>({
        createComment: () => ({}),
        createElement: () => ({}),
        createText: () => ({}),
        insert: () => {},
        remove: () => {},
        patchProp: () => {},
        setElementText: () => {},
        setText: () => {},
        parentNode: () => null,
        nextSibling: () => null,
    });
    const panel = defineComponent({
        setup() {
            state = useGameEventEntry(props);
            return () => null;
        },
    });
    const app = renderer.createApp({
        render: () =>
            h(App, {
                initialComponent: panel as DefineComponent,
                initialPage: {
                    component: 'GameEventShow',
                    url: '/game-events/1',
                    version: 'test',
                    rescuedProps: [],
                    flash: {},
                    rememberedState: {},
                    props: {
                        locale: 'en',
                        errors: {},
                        auth: { user: { coins: 100 } },
                    },
                },
            }),
    });
    app.mount({});
    cleanup = () => app.unmount();
    return { props, state };
}

it('preserves a chosen class across refreshed data and chooses a valid class for a different dog', async () => {
    const { props, state } = mountEntry();
    expect(state.form.plan.exhibition_class).toBe('junior');
    state.form.plan.exhibition_class = 'open';

    props.dogs = [dog(1), dog(2)];
    await nextTick();
    expect(state.form.plan.exhibition_class).toBe('open');

    state.form.pet_id = 2;
    await nextTick();
    expect(state.form.plan.exhibition_class).toBe('veteran');
});

it.each(['agility', 'progeny'] as const)(
    'does not submit an exhibition class to %s',
    (discipline) => {
        const { state } = mountEntry(discipline);
        expect(state.form.data().plan).not.toHaveProperty('exhibition_class');
    },
);

it.each([
    [
        'conformation',
        'conformation',
        { stages: ['balanced'], exhibition_class: 'open' },
    ],
    ['agility', 'agility', { stages: ['balanced'], sport_class: 'agility_1' }],
    [
        'nosework',
        'nosework',
        { stages: ['balanced'], sport_class: 'nw_0', program: 'pr1' },
    ],
    [
        'nosework',
        'odor_test',
        {
            stages: ['balanced'],
            sport_class: 'nw_test',
            program: 'pr1',
            test_type: 'birch',
        },
    ],
    [
        'canicross',
        'canicross',
        {
            stages: ['balanced'],
            sport_class: 'canicross_open',
            handler_category: 'open',
        },
    ],
    [
        'progeny',
        'progeny',
        {
            stages: ['balanced', 'balanced', 'balanced'],
            offspring_ids: [7, 8, 9],
        },
    ],
] as [EventDiscipline, string, GameEventPlan][])(
    'sends only fields belonging to %s in the %s format',
    async (discipline, format, expected) => {
        const { props, state } = mountEntry(discipline);
        props.event.regulation = {
            version: 1,
            system: 'rkf',
            rank: 'qualifying',
            rankLabel: 'Qualifying',
            country: 'RU',
            region: '',
            judgeId: 'judge-1',
            judgeName: 'Judge One',
            format,
        };
        props.entry = {
            id: 9,
            petId: 1,
            name: 'Dog',
            ownerName: 'owner',
            isNpc: false,
            division: 'stored',
            status: 'registered',
            gearIds: [],
            rank: null,
            prize: 0,
            result: null,
            plan: {
                offspring_ids: [7, 8, 9],
                exhibition_class: 'open',
                sport_class: 'agility_1',
                program: 'pr1',
                test_type: 'birch',
                handler_category: 'open',
                ...expected,
            },
        };
        await nextTick();

        expect(state.form.data().plan).toEqual(expected);
    },
);

it('retains a historical class when the entered dog no longer belongs to the viewer', async () => {
    const { props, state } = mountEntry();
    props.entry = {
        id: 9,
        petId: 1,
        name: 'Former dog',
        ownerName: 'seller',
        isNpc: false,
        division: 'open:breed-1:male',
        status: 'completed',
        plan: {
            stages: ['balanced', 'balanced', 'balanced'],
            exhibition_class: 'open',
        },
        gearIds: [],
        rank: 1,
        prize: 100,
        result: null,
    };
    await nextTick();
    props.dogs = [];
    await nextTick();

    expect(state.form.plan.exhibition_class).toBe('open');
});

it('updates the preparation class from junior to intermediate and keeps it after refreshed dog data', async () => {
    const { props, state } = mountEntry();
    expect(state.selectedExhibitionClass?.value?.label).toBe('Junior');

    state.form.plan.exhibition_class = 'intermediate';
    await nextTick();
    expect(state.selectedExhibitionClass?.value?.label).toBe('Intermediate');

    props.dogs = [dog(1), dog(2)];
    await nextTick();
    expect(state.selectedExhibitionClass?.value?.label).toBe('Intermediate');
});

it('uses eligible regulated entry options and preserves the selected programme through refreshed dog data', async () => {
    const { props, state } = mountEntry('nosework');
    props.event.regulation = {
        version: 1,
        system: 'rkf',
        rank: 'qualification',
        rankLabel: 'Qualification trial',
        country: 'RU',
        region: 'Moscow',
        judgeId: 'judge-1',
        judgeName: 'Judge One',
        format: 'nosework',
    };
    const options = [
        {
            key: 'pr1:nw0',
            label: 'Programme 1 · NW-0',
            plan: { sport_class: 'nw0', program: 'pr1' },
        },
        {
            key: 'pr2:nw0',
            label: 'Programme 2 · NW-0',
            plan: {
                sport_class: 'nw0',
                program: 'pr2',
                stages: ['balanced', 'balanced', 'balanced', 'balanced'],
            },
        },
        {
            key: 'pr1:nw3',
            label: 'Programme 1 · NW-3',
            plan: { sport_class: 'nw3', program: 'pr1' },
            eligible: false,
            reason: 'Earn the previous qualification first.',
        },
    ];
    props.dogs = [
        { ...dog(1), entryOptions: options, defaultEntryOption: 'pr1:nw0' },
    ];
    props.event.stages = ['objects', 'interior', 'exterior', 'vehicles'].map(
        (key) => ({
            key,
            label: key,
            options: ['careful', 'balanced', 'bold'],
        }),
    );
    await nextTick();

    expect(state.form.plan.stages).toEqual([
        'balanced',
        'balanced',
        'balanced',
        'balanced',
    ]);
    expect(state.form.plan.program).toBe('pr1');
    props.event.status = 'scheduled';
    props.event.canRegister = true;
    await nextTick();
    state.applyStagePreset(['careful', 'balanced', 'bold']);
    expect(state.form.plan.stages).toEqual([
        'careful',
        'balanced',
        'balanced',
        'bold',
    ]);
    state.selectedEntryOptionKey.value = 'pr2:nw0';
    expect(state.form.data().plan).toEqual({
        stages: ['careful', 'balanced', 'balanced', 'bold'],
        sport_class: 'nw0',
        program: 'pr2',
    });
    props.dogs = [
        {
            ...dog(1),
            entryOptions: [...options],
            defaultEntryOption: 'pr1:nw0',
        },
    ];
    await nextTick();
    expect(state.selectedEntryOption?.value?.label).toBe('Programme 2 · NW-0');
    state.selectedEntryOptionKey.value = 'pr1:nw3';
    expect(state.form.plan.program).toBe('pr2');
    expect(state.form.plan.sport_class).toBe('nw0');
});

it('clears a stale regulated classification when switching dogs and keeps a historical plan without its dog', async () => {
    const { props, state } = mountEntry('agility');
    props.event.regulation = {
        version: 1,
        system: 'fci',
        rank: 'international',
        rankLabel: 'International agility trial',
        country: 'BE',
        region: '',
        judgeId: 'judge-1',
        judgeName: 'Judge One',
        format: 'agility',
    };
    props.dogs = [
        {
            ...dog(1),
            entryOptions: [
                {
                    key: 'a1',
                    label: 'Agility 1 · L',
                    plan: { sport_class: 'a1' },
                },
            ],
            defaultEntryOption: 'a1',
        },
        {
            ...dog(2),
            entryOptions: [
                {
                    key: 'j2',
                    label: 'Jumping 2 · M',
                    plan: { sport_class: 'a2' },
                },
            ],
            defaultEntryOption: 'j2',
        },
    ];
    await nextTick();
    state.form.pet_id = 2;
    await nextTick();
    expect(state.form.plan.sport_class).toBe('a2');
    expect(state.form.plan).not.toHaveProperty('test_type');
    props.entry = {
        id: 9,
        petId: 2,
        name: 'Former dog',
        ownerName: 'seller',
        isNpc: false,
        division: 'agility:a2:M',
        status: 'completed',
        plan: { stages: ['careful'], sport_class: 'a2' },
        gearIds: [],
        rank: 1,
        prize: 100,
        result: null,
    };
    await nextTick();
    props.dogs = [];
    await nextTick();
    expect(state.form.plan.sport_class).toBe('a2');
});
