<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Award,
    Bell,
    BellCheck,
    Check,
    CheckCheck,
    Coins,
    HeartCrack,
    LogIn,
    PawPrint,
    ShieldCheck,
    Sparkles,
    Trophy,
    X,
} from '@lucide/vue';
import {
    PopoverClose,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { useId } from 'vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { useSystemNotifications } from '@/composables/useSystemNotifications';

const { t, locale, number } = useI18n();
const titleId = useId();
const {
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
} = useSystemNotifications();
const icons = {
    login: LogIn,
    password_changed: ShieldCheck,
    password_reset: ShieldCheck,
    site_update: Sparkles,
    achievement: Award,
    pet_death: HeartCrack,
    show_result: Trophy,
    competition_result: Trophy,
    breeding_success: PawPrint,
    pet_sold: Coins,
};
const localized = (value: Record<'ru' | 'en', string>) => value[locale.value];
const formatDate = (value: string) =>
    new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
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
                        <p>{{ t('Gameplay and account updates') }}</p>
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
                    v-if="!loaded && loading"
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
                    v-else-if="loaded && items.length === 0 && !nextCursor"
                    class="notification-empty"
                >
                    <BellCheck :size="30" aria-hidden="true" />
                    <strong>{{ t('No unread notifications') }}</strong>
                    <p>
                        {{
                            t(
                                'Achievements, dog updates, event results and sales will appear here.',
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
                        <span
                            class="notification-icon"
                            :class="`notification-icon-${notification.kind}`"
                        >
                            <component
                                :is="icons[notification.kind] ?? Bell"
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
                            <div class="notification-item-actions">
                                <Link
                                    v-if="notification.actionUrl"
                                    :href="notification.actionUrl"
                                    class="notification-action"
                                    :aria-label="
                                        t('View details: {title}', {
                                            title: localized(
                                                notification.title,
                                            ),
                                        })
                                    "
                                >
                                    {{ t('Details') }}
                                    <ArrowUpRight
                                        :size="15"
                                        aria-hidden="true"
                                    />
                                </Link>
                                <Button
                                    v-if="!notification.readAt"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="notification-read-button"
                                    :disabled="busy"
                                    :aria-label="
                                        t('Mark as read: {title}', {
                                            title: localized(
                                                notification.title,
                                            ),
                                        })
                                    "
                                    @click="markRead(notification)"
                                >
                                    <Check :size="14" aria-hidden="true" />
                                    {{ t('Mark as read') }}
                                </Button>
                            </div>
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
                            loading
                                ? t('Loading notifications')
                                : t('Older notifications')
                        }}
                    </Button>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
