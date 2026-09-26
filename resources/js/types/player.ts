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
    joinedAt: string | null;
};

export type PlayerDog = {
    id: number;
    name: string;
    breed: string;
    portraitId: number | null;
    backgroundId: number | null;
};

export type AvatarLimits = {
    max_kilobytes: number;
    max_dimension: number;
    stored_dimension: number;
};
