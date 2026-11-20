export type TelegramConnectionData = {
    available: boolean;
    status:
        | 'unavailable'
        | 'disconnected'
        | 'pending'
        | 'expired'
        | 'connected';
    account: {
        firstName: string;
        username: string | null;
        connectedAt: string;
    } | null;
    link: {
        url: string;
        expiresAt: string;
    } | null;
    botUrl: string | null;
    serverNow: string;
};
