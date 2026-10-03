export type PlayerProfile = {
    name: string;
    username: string;
    avatarVersion: string | null;
    bio: string | null;
    level: number;
    experience: string;
    progress: {
        levelExperience: string;
        requiredExperience: string;
        remainingExperience: string;
        percent: number;
        nextLevel: number;
    };
    statistics: {
        actionsCount: number;
        feedingCount: number;
        wateringCount: number;
        playCount: number;
        groomingCount: number;
        restCount: number;
        skillLessonsCount: number;
        workCount: number;
        veterinaryCount: number;
        activeDays: number;
        lastActionAt: string | null;
    };
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

export type DailyWork = {
    completedToday: boolean;
    canWork: boolean;
    progress: number;
    streakLength: number;
    resetsAt: string;
    timezone: string;
    earnedCoins: number;
    earnedGems: number;
    jobs: {
        id: number;
        name: string;
        description: string;
        coins: number;
        gems: number;
    }[];
};
