export type PlayerProfile = {
    name: string;
    username: string;
    avatarVersion: string | null;
    bio: string | null;
    level: number;
    experience: number;
    dogsCount: number;
    exhibitionWins: number;
    competitionWins: number;
    walksCount: number;
    trainingsCount: number;
};

export type AvatarLimits = {
    max_kilobytes: number;
    max_dimension: number;
    stored_dimension: number;
};
