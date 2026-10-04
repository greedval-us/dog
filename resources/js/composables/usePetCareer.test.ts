import { createRenderer, defineComponent, ref } from 'vue';
import { afterEach, beforeEach, expect, it, vi } from 'vite-plus/test';
import { usePetCareer } from './usePetCareer';
import type { PetCareer } from '@/types/pet-career';

const get = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/vue3', () => ({ router: { get } }));
let cleanup: (() => void) | undefined;

function mountCareer(career?: PetCareer) {
    const prop = ref(career);
    let state: ReturnType<typeof usePetCareer> | undefined;
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
            setup() {
                state = usePetCareer(
                    () => 7,
                    () => prop.value,
                );
                return () => null;
            },
        }),
    );
    app.mount({});
    cleanup = () => app.unmount();
    return { state: state!, prop };
}

function careerFixture(petId = 7): PetCareer {
    return {
        petId,
        titles: [],
        summary: {
            competitionStarts: 0,
            competitionWins: 0,
            exhibitionStarts: 0,
            exhibitionWins: 0,
            podiums: 0,
            cups: 0,
        },
        results: { entries: [], nextCursor: null, previousCursor: null },
    };
}
beforeEach(() => {
    get.mockReset();
});
afterEach(() => {
    cleanup?.();
    cleanup = undefined;
});

it('loads only career when its mounted tab has no data, preserving the other dashboard state', () => {
    const { state } = mountCareer();

    expect(state.loading.value).toBe(true);
    expect(get).toHaveBeenCalledExactlyOnceWith(
        '/dashboard?pet=7',
        {},
        expect.objectContaining({
            only: ['career'],
            preserveState: true,
            preserveScroll: true,
            preserveErrors: true,
        }),
    );
});

it('reuses previously loaded data when the tab is reopened', () => {
    const career = careerFixture();
    const { state } = mountCareer(career);

    expect(state.data.value).toEqual(career);
    expect(get).not.toHaveBeenCalled();
});

it('does not expose another dog’s cached data and requests the selected dog', () => {
    const { state } = mountCareer(careerFixture(99));

    expect(state.data.value).toBeUndefined();
    expect(get).toHaveBeenCalledWith(
        '/dashboard?pet=7',
        {},
        expect.objectContaining({ only: ['career'] }),
    );
});

it('loads a cursor page without replacing unrelated props or duplicating a pending request', () => {
    const { state } = mountCareer(careerFixture());
    state.load('older+page');
    state.load('duplicate');

    expect(get).toHaveBeenCalledExactlyOnceWith(
        '/dashboard?pet=7&career_cursor=older%2Bpage',
        {},
        expect.objectContaining({ only: ['career'], replace: true }),
    );
});

it('keeps a failed request in the tab and allows a retry', () => {
    const { state } = mountCareer();
    const options = get.mock.calls[0][2];
    expect(options.onHttpException()).toBe(false);
    options.onFinish();

    expect(state.failed.value).toBe(true);
    expect(state.loading.value).toBe(false);
    state.load();
    expect(state.failed.value).toBe(false);
    expect(get).toHaveBeenCalledTimes(2);
});

it('retries the requested history page after a failure', () => {
    const { state } = mountCareer(careerFixture());
    state.load('older');
    const options = get.mock.calls[0][2];
    options.onNetworkError();
    options.onFinish();
    state.retry();

    expect(get).toHaveBeenNthCalledWith(
        2,
        '/dashboard?pet=7&career_cursor=older',
        {},
        expect.objectContaining({ only: ['career'] }),
    );
});

it('cancels a request when switching away and ignores its late callbacks', () => {
    const { state } = mountCareer();
    const options = get.mock.calls[0][2];
    const cancel = vi.fn();
    options.onCancelToken({ cancel });
    cleanup?.();
    cleanup = undefined;
    options.onHttpException();
    options.onSuccess({ props: { career: careerFixture(99) } });

    expect(cancel).toHaveBeenCalledOnce();
    expect(state.failed.value).toBe(false);
});
