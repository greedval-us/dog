import {
    createRenderer,
    defineComponent,
    h,
    nextTick,
    ref,
    ssrContextKey,
} from 'vue';
import { afterEach, expect, it, vi } from 'vite-plus/test';
import PetHistory from './PetHistory.vue';
import type { PlayerPet } from '@/types/pet';
import type { PetHistoryPage } from '@/types/pet-history';

const request = vi.hoisted(() => ({
    get: vi.fn(),
    post: vi.fn(),
    cancel: vi.fn(),
    processing: false,
}));
vi.mock('@inertiajs/vue3', async () => {
    const actual =
        await vi.importActual<typeof import('@inertiajs/vue3')>(
            '@inertiajs/vue3',
        );
    return {
        ...actual,
        useHttp: () => request,
        usePage: () => ({ props: { locale: 'en' } }),
    };
});
let cleanup: (() => void) | undefined;
afterEach(() => {
    cleanup?.();
    cleanup = undefined;
    vi.resetAllMocks();
});

function petFixture(): PlayerPet {
    return {
        id: 7,
        name: 'Rey',
        breed: 'Shepherd',
        sex: 'male',
        coatColor: 'black',
        size: 'large',
        generation: 1,
        description: null,
        bornAt: '2026-08-01',
        isPurebred: true,
        isFavorite: true,
        hasPedigree: false,
        lifecycle: {
            status: 'active',
            archivedAt: null,
            canRetire: false,
            retirementEligibleAt: '2026-11-01T00:00:00Z',
            automaticRetirementAt: '2027-02-01T00:00:00Z',
        },
        traits: [],
        states: {
            health: 100,
            energy: 100,
            satiety: 100,
            hydration: 100,
            mood: 100,
            cleanliness: 100,
            bond: 100,
        },
        energy: { value: 100, maximum: 100 },
        stats: {
            endurance: { value: 20, potential: 100 },
            speed: { value: 20, potential: 100 },
            strength: { value: 20, potential: 100 },
            agility: { value: 20, potential: 100 },
            obedience: { value: 20, potential: 100 },
            intelligence: { value: 20, potential: 100 },
        },
    };
}

it('marks history refreshed by a changed pet as background while keeping manual refresh active', async () => {
    const response: PetHistoryPage = {
        data: [],
        events: [],
        nextCursor: null,
        previousCursor: null,
        kind: 'action',
        period: 7,
    };
    request.get.mockResolvedValue(response);
    request.post.mockResolvedValue(undefined);
    const pet = ref(petFixture());
    const history = ref<{ refresh: () => Promise<void> }>();
    const HistoryLifecycle = Object.assign({}, PetHistory, {
        render: () => null,
    });
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
    const app = renderer.createApp(
        defineComponent({
            setup: () => () =>
                h(HistoryLifecycle, { pet: pet.value, ref: history }),
        }),
    );
    app.provide(ssrContextKey, {});
    app.mount({});
    cleanup = () => app.unmount();
    await nextTick();
    await new Promise<void>((resolve) => setTimeout(resolve, 0));
    request.get.mockClear();
    request.post.mockClear();

    pet.value = { ...pet.value, name: 'Rey after polling' };
    await nextTick();
    await new Promise<void>((resolve) => setTimeout(resolve, 0));

    expect(request.post).toHaveBeenCalledExactlyOnceWith('/pets/7/thoughts', {
        headers: { 'X-DogLive-Background': '1' },
    });
    expect(request.get).toHaveBeenCalledExactlyOnceWith(
        '/pets/7/history?kind=action&period=7',
        { headers: { 'X-DogLive-Background': '1' } },
    );

    request.get.mockClear();
    request.post.mockClear();
    await history.value?.refresh();

    expect(request.post).toHaveBeenCalledExactlyOnceWith(
        '/pets/7/thoughts',
        undefined,
    );
    expect(request.get).toHaveBeenCalledExactlyOnceWith(
        '/pets/7/history?kind=action&period=7',
        undefined,
    );
});
