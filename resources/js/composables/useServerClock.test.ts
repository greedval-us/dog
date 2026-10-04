import { createRenderer, defineComponent, nextTick, ref } from 'vue';
import { afterEach, beforeEach, expect, it, vi } from 'vite-plus/test';
import { useServerClock } from './useServerClock';

let cleanup: (() => void) | undefined;

function mountClock(serverNow: () => string | undefined) {
    let clock!: ReturnType<typeof useServerClock>;
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
                clock = useServerClock(serverNow);
                return () => null;
            },
        }),
    );
    app.mount({});
    cleanup = () => app.unmount();
    return clock;
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

it('advances from server time without following a changed device wall clock', () => {
    const elapsed = vi.spyOn(performance, 'now').mockReturnValue(10_000);
    const wallClock = vi
        .spyOn(Date, 'now')
        .mockReturnValue(Date.parse('2099-01-01T00:00:00Z'));
    const clock = mountClock(() => '2026-10-04T12:00:00Z');

    wallClock.mockReturnValue(Date.parse('1999-01-01T00:00:00Z'));
    elapsed.mockReturnValue(15_000);
    vi.advanceTimersByTime(1000);

    expect(clock.now.value).toBe(Date.parse('2026-10-04T12:00:05Z'));
});

it('replaces elapsed time with the fresh server anchor after a partial update', async () => {
    const serverNow = ref('2026-10-04T12:00:00Z');
    const elapsed = vi.spyOn(performance, 'now').mockReturnValue(100);
    const clock = mountClock(() => serverNow.value);
    elapsed.mockReturnValue(15_100);
    vi.advanceTimersByTime(1000);

    serverNow.value = '2026-10-04T12:01:00Z';
    await nextTick();
    elapsed.mockReturnValue(16_100);
    vi.advanceTimersByTime(1000);

    expect(clock.now.value).toBe(Date.parse('2026-10-04T12:01:01Z'));
    expect(vi.getTimerCount()).toBe(1);
});

it('waits for a server anchor and releases its timer when unmounted', async () => {
    const serverNow = ref<string>();
    vi.spyOn(performance, 'now').mockReturnValue(100);
    const clock = mountClock(() => serverNow.value);
    expect(vi.getTimerCount()).toBe(0);

    serverNow.value = '2026-10-04T12:00:00Z';
    await nextTick();
    expect(clock.now.value).toBe(Date.parse('2026-10-04T12:00:00Z'));
    expect(vi.getTimerCount()).toBe(1);
    cleanup?.();
    cleanup = undefined;

    expect(vi.getTimerCount()).toBe(0);
});
