import { router } from '@inertiajs/vue3';
import { createRenderer, defineComponent, nextTick } from 'vue';
import { afterEach, expect, it, vi } from 'vite-plus/test';
import { usePetCareActivity } from './usePetCareActivity';
import type { PetCare } from '@/types/pet-care';

let cleanup: (() => void) | undefined;

function mountActivity() {
    let activity!: ReturnType<typeof usePetCareActivity>;
    const care: PetCare = {
        token: 'next-action',
        modifierKeys: [],
        buffs: [],
        debuffs: [],
        recentIncidents: [],
        serverNow: '2026-10-20T12:00:00Z',
        blocked: false,
        busy: true,
        working: false,
        cooldowns: {},
        options: [],
        active: {
            token: 'completed-action',
            label: 'Feed',
            startedAt: '2026-10-20T12:00:00Z',
            endsAt: '2026-10-20T12:00:05Z',
            effects: {},
            statGains: null,
        },
    };
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
                activity = usePetCareActivity(
                    () => 7,
                    () => care,
                    () => true,
                );
                return () => null;
            },
        }),
    );
    app.mount({});
    cleanup = () => app.unmount();
    return activity;
}

afterEach(() => {
    cleanup?.();
    cleanup = undefined;
    vi.restoreAllMocks();
    vi.useRealTimers();
});

it('marks automatic completion as background while preserving an unmarked manual retry', async () => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
    const elapsed = vi.spyOn(performance, 'now').mockReturnValue(0);
    const post = vi
        .spyOn(router, 'post')
        .mockImplementation((_url, _data, options) => {
            options?.onFinish?.({} as never);
        });
    const activity = mountActivity();

    elapsed.mockReturnValue(5_000);
    vi.advanceTimersByTime(5_000);
    await nextTick();

    expect(post).toHaveBeenCalledExactlyOnceWith(
        '/pets/7/care/complete',
        { token: 'completed-action' },
        expect.objectContaining({
            headers: { 'X-DogLive-Background': '1' },
            only: ['pet', 'care'],
        }),
    );
    expect(activity.completionFailed.value).toBe(true);

    activity.finishActivity();

    expect(post).toHaveBeenCalledTimes(2);
    expect(post.mock.calls[1][2]?.headers).toBeUndefined();
    expect(post.mock.calls[1][1]).toEqual({ token: 'completed-action' });
});
