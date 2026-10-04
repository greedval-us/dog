import { router, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
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
        { only: ['event', 'entry', 'serverNow'] },
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
            router.reload({ only: ['auth'] });
        }
    });
}
