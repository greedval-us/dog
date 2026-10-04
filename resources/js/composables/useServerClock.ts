import { onMounted, onUnmounted, ref, watch } from 'vue';

export function useServerClock(
    serverNow: () => string | undefined,
    interval = 1000,
) {
    const now = ref(0);
    let serverAnchor = 0;
    let elapsedAnchor = 0;
    let mounted = false;
    let anchored = false;
    let timer: ReturnType<typeof setInterval> | undefined;

    function stop(): void {
        clearInterval(timer);
        timer = undefined;
    }

    function start(): void {
        if (!mounted || !anchored || timer !== undefined) return;
        timer = setInterval(() => {
            now.value =
                serverAnchor + Math.max(0, performance.now() - elapsedAnchor);
        }, interval);
    }

    watch(
        serverNow,
        (value) => {
            const timestamp = value ? Date.parse(value) : NaN;
            anchored = Number.isFinite(timestamp);
            if (!anchored) {
                stop();
                now.value = 0;
                return;
            }
            serverAnchor = timestamp;
            elapsedAnchor = performance.now();
            now.value = serverAnchor;
            start();
        },
        { immediate: true },
    );
    onMounted(() => {
        mounted = true;
        start();
    });
    onUnmounted(stop);

    return { now };
}
