import { useHttp, usePage, usePoll } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import { backgroundRequestHeaders } from '@/lib/backgroundRequests';
import { index, readAll, update } from '@/routes/notifications';
import type {
    SystemNotification,
    SystemNotificationPage,
} from '@/types/notification';

export function useSystemNotifications() {
    const page = usePage();
    const open = ref(false);
    const items = ref<SystemNotification[]>([]);
    const nextCursor = ref<string | null>(null);
    const unreadCount = ref(page.props.systemNotificationUnreadCount);
    const loadError = ref(false);
    const actionError = ref(false);
    const loaded = ref(false);
    const listRequest = useHttp<Record<string, never>, SystemNotificationPage>(
        {},
    );
    const readRequest = useHttp<
        Record<string, never>,
        { readAt: string; unreadCount: number }
    >({});
    const busy = computed(
        () => listRequest.processing || readRequest.processing,
    );
    const loading = computed(() => listRequest.processing);
    let generation = 0;
    let active = true;

    usePoll(
        60_000,
        {
            only: ['systemNotificationUnreadCount'],
            headers: backgroundRequestHeaders,
            onSuccess: (response) => {
                unreadCount.value =
                    response.props.systemNotificationUnreadCount;
            },
        },
        { mode: 'rest' },
    );

    watch(
        () => page.props.systemNotificationUnreadCount,
        (count) => {
            unreadCount.value = count;
        },
    );
    watch(
        () => page.url,
        () => {
            open.value = false;
            unreadCount.value = page.props.systemNotificationUnreadCount;
        },
    );
    watch(open, (value) => {
        if (value) {
            void load();
        } else {
            generation++;
            listRequest.cancel();
        }
    });
    onUnmounted(() => {
        active = false;
        generation++;
        listRequest.cancel();
        readRequest.cancel();
    });

    async function load(older = false) {
        if (busy.value || (older && !nextCursor.value)) return;
        const currentGeneration = ++generation;
        loadError.value = false;
        actionError.value = false;
        if (!older) loaded.value = false;
        try {
            const response = await listRequest.get(
                index.url({
                    query: older ? { cursor: nextCursor.value } : {},
                }),
            );
            if (!active || currentGeneration !== generation) return;
            items.value = older
                ? [...items.value, ...response.items]
                : response.items;
            nextCursor.value = response.nextCursor;
            unreadCount.value = response.unreadCount;
            loaded.value = true;
        } catch {
            if (active && currentGeneration === generation)
                loadError.value = true;
        }
    }

    async function markRead(notification?: SystemNotification) {
        if (busy.value) return;
        actionError.value = false;
        try {
            const response = await readRequest.patch(
                notification ? update.url(notification.id) : readAll.url(),
            );
            if (!active) return;
            items.value = notification
                ? items.value.filter((item) => item.id !== notification.id)
                : [];
            if (!notification) nextCursor.value = null;
            unreadCount.value = response.unreadCount;
        } catch {
            if (active) actionError.value = true;
        }
    }

    return {
        open,
        items,
        nextCursor,
        unreadCount,
        loadError,
        actionError,
        loaded,
        busy,
        loading,
        load,
        markRead,
    };
}
