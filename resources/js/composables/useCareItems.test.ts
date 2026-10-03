import { createRenderer, defineComponent } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { useCareItems } from './useCareItems';
import type { CareItem, CareItemSelection, CareOption } from '@/types/pet-care';

const request = vi.hoisted(() => ({ get: vi.fn(), cancel: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ useHttp: () => request }));

function option(attributes: Partial<CareOption> = {}): CareOption {
    return {
        id: 'meal',
        group: 'feed',
        label: 'Meal',
        duration: 60,
        cooldown: 60,
        energy: 0,
        baseEnergy: 0,
        grantedEffects: [],
        statusRecovery: {},
        optional: [],
        requirements: ['food'],
        uses: { food: 3 },
        effects: {},
        reason: null,
        reasonCode: null,
        gainsByQuality: {},
        ...attributes,
    };
}
function item(attributes: Partial<CareItem> = {}): CareItem {
    return {
        id: 1,
        category: 'food',
        name: 'Food',
        quality: 5,
        bonuses: {},
        grantedEffects: [],
        risks: [],
        bonus: {},
        remainingUses: 10,
        ...attributes,
    };
}
function pendingResponse() {
    let resolve!: (value: {
        items: CareItem[];
        nextCursor: string | null;
    }) => void;
    const promise = new Promise<{
        items: CareItem[];
        nextCursor: string | null;
    }>((settle) => {
        resolve = settle;
    });
    return { promise, resolve };
}
function mountLoader(
    selected: () => CareOption | undefined,
    selection: CareItemSelection,
) {
    let loader!: ReturnType<typeof useCareItems>;
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
                loader = useCareItems(selected, () => selection);
                return () => null;
            },
        }),
    );
    app.mount({});
    return { loader, unmount: () => app.unmount() };
}

beforeEach(() => {
    request.get.mockReset();
    request.cancel.mockReset();
});

describe('care supply loading', () => {
    it('ignores a late response after choosing another variant', async () => {
        const first = pendingResponse();
        const toy = item({ id: 2, category: 'toys' });
        request.get
            .mockReturnValueOnce(first.promise)
            .mockResolvedValueOnce({ items: [toy], nextCursor: null });
        let selected = option();
        const selection: CareItemSelection = {};
        const { loader, unmount } = mountLoader(() => selected, selection);

        const firstLoad = loader.load();
        selected = option({
            id: 'toy',
            group: 'play',
            requirements: ['toys'],
            uses: { toys: 1 },
        });
        await loader.load();
        first.resolve({ items: [item()], nextCursor: 'stale-cursor' });
        await firstLoad;

        expect(loader.items.value).toEqual([toy]);
        expect(loader.cursors.value).toEqual({ toys: null });
        expect(selection).toEqual({ toys: 2 });
        expect(loader.failed.value).toBe(false);
        unmount();
    });

    it('cancels pending loading on unmount without accepting its later result', async () => {
        const pending = pendingResponse();
        request.get.mockReturnValueOnce(pending.promise);
        const selection: CareItemSelection = {};
        const { loader, unmount } = mountLoader(() => option(), selection);

        const loading = loader.load();
        request.cancel.mockClear();
        unmount();
        pending.resolve({ items: [item()], nextCursor: null });
        await loading;

        expect(request.cancel).toHaveBeenCalledOnce();
        expect(loader.items.value).toEqual([]);
        expect(selection).toEqual({});
        expect(loader.loading.value).toBe(false);
    });

    it('retries failed supplies and selects a required item once the response succeeds', async () => {
        request.get
            .mockRejectedValueOnce(new Error('Connection lost'))
            .mockResolvedValueOnce({ items: [item()], nextCursor: null });
        const selection: CareItemSelection = {};
        const { loader, unmount } = mountLoader(() => option(), selection);

        await loader.load();
        expect(loader.failed.value).toBe(true);
        expect(loader.loading.value).toBe(false);
        await loader.load();

        expect(loader.failed.value).toBe(false);
        expect(selection).toEqual({ food: 1 });
        unmount();
    });

    it('uses category cursors and required uses while omitting duplicate inventory items', async () => {
        request.get
            .mockResolvedValueOnce({ items: [item()], nextCursor: 'food-next' })
            .mockResolvedValueOnce({
                items: [item(), item({ id: 2 })],
                nextCursor: null,
            });
        const { loader, unmount } = mountLoader(() => option(), {});

        await loader.load();
        await loader.load('food');

        expect(request.get).toHaveBeenLastCalledWith(
            '/care-items?category=food&uses=3&cursor=food-next',
        );
        expect(loader.items.value.map((entry) => entry.id)).toEqual([1, 2]);
        expect(loader.cursors.value.food).toBeNull();
        unmount();
    });

    it('loads optional supplies without automatically consuming them', async () => {
        request.get
            .mockResolvedValueOnce({ items: [item()], nextCursor: null })
            .mockResolvedValueOnce({
                items: [item({ id: 2, category: 'sports' })],
                nextCursor: null,
            });
        const selection: CareItemSelection = {};
        const { loader, unmount } = mountLoader(
            () => option({ optional: ['sports'] }),
            selection,
        );

        await loader.load();

        expect(loader.items.value.map((entry) => entry.id)).toEqual([1, 2]);
        expect(selection).toEqual({ food: 1 });
        unmount();
    });
});
