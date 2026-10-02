<script setup lang="ts">
import { useHttp, usePage } from '@inertiajs/vue3';
import {
    Bell,
    BellCheck,
    Check,
    CheckCheck,
    LogIn,
    ShieldCheck,
    Sparkles,
    X,
} from '@lucide/vue';
import {
    PopoverClose,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, onUnmounted, ref, useId, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { index, readAll, update } from '@/routes/notifications';
import type {
    SystemNotification,
    SystemNotificationPage,
} from '@/types/notification';

const page = usePage();
const { t, locale, number } = useI18n();
const titleId = useId();
const open = ref(false);
const items = ref<SystemNotification[]>([]);
const nextCursor = ref<string | null>(null);
const unreadCount = ref(page.props.systemNotificationUnreadCount);
const loadError = ref(false);
const actionError = ref(false);
const loaded = ref(false);
const listRequest = useHttp<Record<string, never>, SystemNotificationPage>({});
const readRequest = useHttp<
    Record<string, never>,
    { readAt: string; unreadCount: number }
>({});
const busy = computed(() => listRequest.processing || readRequest.processing);
let generation = 0;
let active = true;
const icons = {
    login: LogIn,
    password_changed: ShieldCheck,
    password_reset: ShieldCheck,
    site_update: Sparkles,
};
const localized = (value: Record<'ru' | 'en', string>) => value[locale.value];
const formatDate = (value: string) =>
    new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

watch(
    () => page.props,
    (props) => {
        unreadCount.value = props.systemNotificationUnreadCount;
    },
);
watch(
    () => page.url,
    () => {
        open.value = false;
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
        if (active && currentGeneration === generation) loadError.value = true;
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
        items.value = items.value.map((item) =>
            !notification || item.id === notification.id
                ? { ...item, readAt: item.readAt ?? response.readAt }
                : item,
        );
        unreadCount.value = response.unreadCount;
    } catch {
        if (active) actionError.value = true;
    }
}
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="plain"
                class="notification-bell"
                :aria-label="
                    unreadCount > 0
                        ? t('Notifications, {count} unread', {
                              count: number(unreadCount),
                          })
                        : t('Notifications')
                "
                :title="t('Notifications')"
            >
                <Bell :size="20" aria-hidden="true" />
                <span
                    v-if="unreadCount > 0"
                    class="notification-count"
                    aria-hidden="true"
                >
                    {{ unreadCount > 99 ? '99+' : number(unreadCount) }}
                </span>
            </Button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                align="end"
                :side-offset="10"
                :collision-padding="12"
                class="notification-panel"
                :aria-labelledby="titleId"
            >
                <div class="notification-header">
                    <div>
                        <h2 :id="titleId">{{ t('Notifications') }}</h2>
                        <p>{{ t('Account security and site updates') }}</p>
                    </div>
                    <PopoverClose as-child>
                        <Button
                            type="button"
                            variant="plain"
                            size="icon"
                            :aria-label="t('Close notifications')"
                        >
                            <X :size="18" aria-hidden="true" />
                        </Button>
                    </PopoverClose>
                </div>
                <div class="notification-toolbar">
                    <span>{{
                        t('{count} unread', { count: number(unreadCount) })
                    }}</span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="busy || !loaded || unreadCount === 0"
                        @click="markRead()"
                    >
                        <CheckCheck :size="16" aria-hidden="true" />
                        {{ t('Mark all as read') }}
                    </Button>
                </div>
                <p v-if="actionError" class="notification-error" role="alert">
                    {{
                        t(
                            'Could not mark notifications as read. Please try again.',
                        )
                    }}
                </p>
                <div
                    v-if="!loaded && listRequest.processing"
                    class="notification-loading"
                    role="status"
                >
                    <span class="notification-skeleton"></span>
                    <span class="notification-skeleton"></span>
                    <span class="notification-skeleton"></span>
                    <span class="sr-only">{{
                        t('Loading notifications')
                    }}</span>
                </div>
                <div
                    v-else-if="loaded && items.length === 0"
                    class="notification-empty"
                >
                    <BellCheck :size="30" aria-hidden="true" />
                    <strong>{{ t('No notifications yet') }}</strong>
                    <p>
                        {{
                            t(
                                'Your sign-ins, password changes and site updates will appear here.',
                            )
                        }}
                    </p>
                </div>
                <ol
                    v-else-if="loaded"
                    class="notification-list"
                    :aria-busy="busy"
                >
                    <li
                        v-for="notification in items"
                        :key="notification.id"
                        class="notification-item"
                        :class="{ 'notification-unread': !notification.readAt }"
                    >
                        <span class="notification-icon">
                            <component
                                :is="icons[notification.kind]"
                                :size="18"
                                aria-hidden="true"
                            />
                        </span>
                        <div class="notification-copy">
                            <div class="notification-item-title">
                                <h3>{{ localized(notification.title) }}</h3>
                                <span
                                    v-if="!notification.readAt"
                                    class="notification-unread-label"
                                    >{{ t('Unread') }}</span
                                >
                            </div>
                            <p>{{ localized(notification.message) }}</p>
                            <time :datetime="notification.createdAt">{{
                                formatDate(notification.createdAt)
                            }}</time>
                            <Button
                                v-if="!notification.readAt"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="notification-read-button"
                                :disabled="busy"
                                :aria-label="
                                    t('Mark as read: {title}', {
                                        title: localized(notification.title),
                                    })
                                "
                                @click="markRead(notification)"
                            >
                                <Check :size="14" aria-hidden="true" />
                                {{ t('Mark as read') }}
                            </Button>
                        </div>
                    </li>
                </ol>
                <div v-if="loadError" class="notification-retry" role="alert">
                    <p>
                        {{
                            t('Could not load notifications. Please try again.')
                        }}
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="busy"
                        @click="load(loaded)"
                    >
                        {{ t('Try again') }}
                    </Button>
                </div>
                <div
                    v-else-if="loaded && nextCursor"
                    class="notification-footer"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="busy"
                        @click="load(true)"
                    >
                        {{
                            listRequest.processing
                                ? t('Loading notifications')
                                : t('Older notifications')
                        }}
                    </Button>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
