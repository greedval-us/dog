export type PlayerAchievement = {
    id: number;
    code: string;
    name: string;
    description: string;
    imageUrl: string;
    rule: string;
    progress: number;
    target: number;
    unlockedAt: string | null;
};
