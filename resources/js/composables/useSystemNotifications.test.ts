import { createRenderer, defineComponent, nextTick, reactive } from 'vue';
import { afterEach, beforeEach, expect, it, vi } from 'vite-plus/test';
import { useSystemNotifications } from './useSystemNotifications';
import type { SystemNotificationPage } from '@/types/notification';

const mocks = vi.hoisted(() => ({
    usePage: vi.fn(),
    useHttp: vi.fn(),
    usePoll: vi.fn(),
}));
vi.mock('@inertiajs/vue3', () => mocks);

const list = { get: vi.fn(), cancel: vi.fn(), processing: false };
const read = { patch: vi.fn(), cancel: vi.fn(), processing: false };
let state: ReturnType<typeof useSystemNotifications>;
let cleanup: (() => void) | undefined;
const page = reactive({
    url: '/dashboard',
    props: { systemNotificationUnreadCount: 1 },
});
const notification = {
    id: 'notification-1',
    kind: 'achievement' as const,
    title: { en: 'Achievement unlocked', ru: 'Получено достижение' },
    message: { en: 'A new award', ru: 'Новая награда' },
    createdAt: '2026-10-21T10:00:00Z',
    readAt: null,
    actionUrl: '/players/owner/achievements',
};
const response: SystemNotificationPage = {
    items: [notification],
    nextCursor: null,
    unreadCount: 1,
};

function mount() {
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
                state = useSystemNotifications();
                return () => null;
            },
        }),
    );
    app.mount({});
    cleanup = () => app.unmount();
}

async function flush() {
    await nextTick();
    await new Promise<void>((resolve) => setTimeout(resolve, 0));
}

beforeEach(() => {
    vi.resetAllMocks();
    page.url = '/dashboard';
    page.props.systemNotificationUnreadCount = 1;
    mocks.usePage.mockReturnValue(page);
    mocks.useHttp.mockReturnValueOnce(list).mockReturnValueOnce(read);
    list.get.mockResolvedValue(response);
    read.patch.mockResolvedValue({ unreadCount: 0 });
});

afterEach(() => {
    cleanup?.();
    cleanup = undefined;
});

it('refreshes the bell in the background without recording player activity', async () => {
    mount();
    expect(mocks.usePoll).toHaveBeenCalledWith(
        60_000,
        {
            only: ['systemNotificationUnreadCount'],
            headers: { 'X-DogLive-Background': '1' },
            onSuccess: expect.any(Function),
        },
        { mode: 'rest' },
    );
    page.props.systemNotificationUnreadCount = 6;
    await flush();
    expect(state.unreadCount.value).toBe(6);
    expect(list.get).not.toHaveBeenCalled();
});

it('accepts a fresh poll count even when it equals the old page prop after reading', async () => {
    mount();
    state.open.value = true;
    await flush();
    await state.markRead(notification);
    expect(state.unreadCount.value).toBe(0);

    const pollOptions = mocks.usePoll.mock.calls[0][1];
    pollOptions.onSuccess({ props: { systemNotificationUnreadCount: 1 } });
    expect(state.unreadCount.value).toBe(1);
});

it('opens gameplay notifications with their destination and removes only the read item', async () => {
    mount();
    state.open.value = true;
    await flush();
    expect(state.items.value).toEqual([notification]);
    expect(state.loaded.value).toBe(true);

    await state.markRead(notification);
    expect(read.patch).toHaveBeenCalledWith('/notifications/notification-1');
    expect(state.items.value).toEqual([]);
    expect(state.unreadCount.value).toBe(0);
});

it('does not replace the list when an obsolete request completes after closing', async () => {
    let resolve: (response: SystemNotificationPage) => void = () => {};
    list.get.mockReturnValue(
        new Promise<SystemNotificationPage>((done) => {
            resolve = done;
        }),
    );
    mount();
    state.open.value = true;
    await flush();
    state.open.value = false;
    await flush();
    resolve(response);
    await flush();
    expect(state.items.value).toEqual([]);
    expect(list.cancel).toHaveBeenCalled();
});

it('keeps notifications and offers retry if marking as read fails', async () => {
    read.patch.mockRejectedValue(new Error('offline'));
    mount();
    state.open.value = true;
    await flush();
    await state.markRead(notification);
    expect(state.actionError.value).toBe(true);
    expect(state.items.value).toEqual([notification]);
    expect(state.unreadCount.value).toBe(1);
});

it('loads the next page and marks all notifications read while retaining the count contract', async () => {
    list.get.mockResolvedValueOnce({ ...response, nextCursor: 'next-page' });
    mount();
    state.open.value = true;
    await flush();
    list.get.mockResolvedValueOnce({
        ...response,
        items: [{ ...notification, id: 'notification-2' }],
    });
    await state.load(true);
    expect(list.get).toHaveBeenLastCalledWith(
        '/notifications?cursor=next-page',
    );
    expect(state.items.value).toHaveLength(2);

    await state.markRead();
    expect(read.patch).toHaveBeenCalledWith('/notifications/read-all');
    expect(state.items.value).toEqual([]);
    expect(state.nextCursor.value).toBeNull();
});
