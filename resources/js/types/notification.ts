export interface SystemNotification {
    id: string;
    kind: 'login' | 'password_changed' | 'password_reset' | 'site_update';
    title: Record<'ru' | 'en', string>;
    message: Record<'ru' | 'en', string>;
    createdAt: string;
    readAt: string | null;
}

export interface SystemNotificationPage {
    items: SystemNotification[];
    nextCursor: string | null;
    unreadCount: number;
}
