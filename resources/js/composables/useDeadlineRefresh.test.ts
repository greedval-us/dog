import { createRenderer, defineComponent, nextTick, ref } from 'vue';
import { afterEach, beforeEach, expect, it, vi } from 'vite-plus/test';
import { useDeadlineRefresh } from './useDeadlineRefresh';

let cleanup: (() => void) | undefined;

function mountRefresh(
    deadlines: () => string[],
    refresh: () => void,
    blocked: () => boolean = () => false,
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
                useDeadlineRefresh(
                    () => '2026-10-20T12:00:00Z',
                    deadlines,
                    refresh,
                    blocked,
                );
                return () => null;
            },
        }),
    );
    app.mount({});
    cleanup = () => app.unmount();
}

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
});
afterEach(() => {
    cleanup?.();
    cleanup = undefined;
    vi.restoreAllMocks();
    vi.useRealTimers();
});

it('refreshes once at a short deadline and again for a new deadline', async () => {
    const elapsed = vi.spyOn(performance, 'now').mockReturnValue(0);
    const deadlines = ref(['2026-10-20T12:00:10Z']);
    const refresh = vi.fn();
    mountRefresh(() => deadlines.value, refresh);

    elapsed.mockReturnValue(9_000);
    vi.advanceTimersByTime(9_000);
    await nextTick();
    expect(refresh).not.toHaveBeenCalled();

    elapsed.mockReturnValue(10_000);
    vi.advanceTimersByTime(1_000);
    await nextTick();
    expect(refresh).toHaveBeenCalledExactlyOnceWith({
        headers: { 'X-DogLive-Background': '1' },
    });

    elapsed.mockReturnValue(15_000);
    vi.advanceTimersByTime(5_000);
    await nextTick();
    expect(refresh).toHaveBeenCalledTimes(1);

    deadlines.value = ['2026-10-20T12:00:20Z'];
    await nextTick();
    elapsed.mockReturnValue(20_000);
    vi.advanceTimersByTime(5_000);
    await nextTick();
    expect(refresh).toHaveBeenCalledTimes(2);
});

it('waits for an in-progress action before refreshing expired deadlines together', async () => {
    const elapsed = vi.spyOn(performance, 'now').mockReturnValue(0);
    const blocked = ref(true);
    const refresh = vi.fn();
    mountRefresh(
        () => ['2026-10-20T12:00:05Z', '2026-10-20T12:00:10Z'],
        refresh,
        () => blocked.value,
    );

    elapsed.mockReturnValue(10_000);
    vi.advanceTimersByTime(10_000);
    await nextTick();
    expect(refresh).not.toHaveBeenCalled();

    blocked.value = false;
    await nextTick();
    expect(refresh).toHaveBeenCalledTimes(1);
});
