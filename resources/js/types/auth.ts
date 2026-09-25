export type User = {
    id: number;
    name: string;
    username: string;
    email: string;
    avatarVersion: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
