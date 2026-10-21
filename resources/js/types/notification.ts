export interface SystemNotification {
    id: string;
    kind:
        | 'login'
        | 'password_changed'
        | 'password_reset'
        | 'site_update'
        | 'achievement'
        | 'pet_death'
        | 'show_result'
        | 'competition_result'
        | 'breeding_success'
        | 'pet_sold';
    title: Record<'ru' | 'en', string>;
    message: Record<'ru' | 'en', string>;
    createdAt: string;
    readAt: string | null;
    actionUrl: string | null;
}

export interface SystemNotificationPage {
    items: SystemNotification[];
    nextCursor: string | null;
    unreadCount: number;
}
