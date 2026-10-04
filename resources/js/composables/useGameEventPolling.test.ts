import { createRenderer, defineComponent, nextTick, ref } from 'vue';
import { afterEach, beforeEach, expect, it, vi } from 'vite-plus/test';
import { useGameEventPolling } from './useGameEventPolling';
import type { GameEventSummary } from '@/types/game-event';

const poll = vi.hoisted(() => ({
    usePoll: vi.fn(),
    start: vi.fn(),
    stop: vi.fn(),
    reload: vi.fn(),
}));
vi.mock('@inertiajs/vue3', () => ({
    usePoll: poll.usePoll,
    router: { reload: poll.reload },
}));
let cleanup: (() => void) | undefined;

function mountPolling(
    status: () => GameEventSummary['status'],
    pending: () => boolean,
    entryStatus: () => string | undefined = () => undefined,
) {
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
                useGameEventPolling(status, pending, entryStatus);
                return () => null;
            },
        }),
    );
    app.mount({});
    cleanup = () => app.unmount();
}

beforeEach(() => {
    poll.start.mockReset();
    poll.stop.mockReset();
    poll.usePoll.mockReset();
    poll.reload.mockReset();
    poll.usePoll.mockImplementation((_interval, _request, options) => {
        if (options.autoStart !== false) poll.start();
        return { start: poll.start, stop: poll.stop };
    });
});
afterEach(() => {
    cleanup?.();
    cleanup = undefined;
});

it.each(['completed', 'cancelled'] as const)(
    'does not start background requests for an initially %s event',
    (status) => {
        mountPolling(
            () => status,
            () => false,
        );

        expect(poll.start).not.toHaveBeenCalled();
    },
);

it('stops when locked results become completed and stays stopped after a mutation finishes', async () => {
    const status = ref<GameEventSummary['status']>('locked');
    const pending = ref(false);
    mountPolling(
        () => status.value,
        () => pending.value,
    );
    expect(poll.start).toHaveBeenCalledTimes(1);

    status.value = 'completed';
    await nextTick();
    expect(poll.stop).toHaveBeenCalledTimes(1);
    pending.value = true;
    await nextTick();
    pending.value = false;
    await nextTick();

    expect(poll.start).toHaveBeenCalledTimes(1);
});

it('pauses during a mutation and resumes while registration remains active', async () => {
    const pending = ref(false);
    mountPolling(
        () => 'scheduled',
        () => pending.value,
    );

    pending.value = true;
    await nextTick();
    expect(poll.stop).toHaveBeenCalledTimes(1);
    pending.value = false;
    await nextTick();

    expect(poll.start).toHaveBeenCalledTimes(2);
});

it.each([
    ['registered', 'completed'],
    ['frozen', 'completed'],
    ['registered', 'withdrawn'],
    ['frozen', 'withdrawn'],
])(
    'refreshes only auth once when the own entry changes from %s to %s',
    async (previous, current) => {
        const entryStatus = ref(previous);
        mountPolling(
            () => 'locked',
            () => false,
            () => entryStatus.value,
        );

        entryStatus.value = current;
        await nextTick();
        entryStatus.value = current;
        await nextTick();

        expect(poll.reload).toHaveBeenCalledExactlyOnceWith({ only: ['auth'] });
    },
);

it.each(['completed', 'withdrawn', 'cancelled'])(
    'does not repeat the full initial auth refresh for a %s own entry',
    (entryStatus) => {
        mountPolling(
            () => 'completed',
            () => false,
            () => entryStatus,
        );

        expect(poll.reload).not.toHaveBeenCalled();
    },
);

it.each(['frozen', 'cancelled'])(
    'does not refresh auth when the own registered entry changes to %s',
    async (nextStatus) => {
        const entryStatus = ref('registered');
        mountPolling(
            () => 'scheduled',
            () => false,
            () => entryStatus.value,
        );

        entryStatus.value = nextStatus;
        await nextTick();

        expect(poll.reload).not.toHaveBeenCalled();
    },
);
