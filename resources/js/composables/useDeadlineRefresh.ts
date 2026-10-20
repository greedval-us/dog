import { useServerClock } from '@/composables/useServerClock';
import { backgroundRequestHeaders } from '@/lib/backgroundRequests';
import { onMounted, shallowRef, watch } from 'vue';

export function useDeadlineRefresh(
    serverNow: () => string,
    deadlines: () => string[],
    refresh: (options: { headers: Record<string, string> }) => void,
    blocked: () => boolean = () => false,
) {
    const clock = useServerClock(serverNow);
    const mounted = shallowRef(false);
    const requested = new Set<string>();
    onMounted(() => {
        mounted.value = true;
    });
    watch(
        () => [mounted.value, clock.now.value, deadlines(), blocked()] as const,
        ([isMounted, now, dates, isBlocked]) => {
            if (!isMounted || !now || isBlocked) return;
            const expired = dates.filter(
                (date) => !requested.has(date) && Date.parse(date) <= now,
            );
            if (!expired.length) return;
            for (const date of expired) requested.add(date);
            refresh({ headers: backgroundRequestHeaders });
        },
    );
    return clock;
}
