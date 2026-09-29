export type User = {
    id: number;
    name: string;
    username: string;
    email: string;
    avatarVersion: string | null;
    email_verified_at: string | null;
    coins: number;
    gems: number;
};

export type Auth = {
    user: User;
};
