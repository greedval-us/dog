import { router, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { backgroundRequestHeaders } from '@/lib/backgroundRequests';
import type { GameEventEntry, GameEventSummary } from '@/types/game-event';

export function useGameEventPolling(
    status: () => GameEventSummary['status'],
    pending: () => boolean,
    entryStatus: () => GameEventEntry['status'] | undefined,
): void {
    const active = computed(
        () => status() === 'scheduled' || status() === 'locked',
    );
    const poll = usePoll(
        30_000,
        {
            only: ['event', 'entry', 'ownEntries', 'serverNow'],
            headers: backgroundRequestHeaders,
        },
        { mode: 'rest', autoStart: active.value && !pending() },
    );
    watch([active, pending], ([isActive, isPending]) => {
        if (isActive && !isPending) poll.start();
        else poll.stop();
    });
    watch(entryStatus, (current, previous) => {
        if (
            (previous === 'registered' || previous === 'frozen') &&
            (current === 'completed' || current === 'withdrawn')
        ) {
            router.reload({
                only: ['auth'],
                headers: backgroundRequestHeaders,
            });
        }
    });
}
