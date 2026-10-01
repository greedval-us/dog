import type { DogStat } from '@/types/pet';

export type SkillLevel = {
    price: number;
    requirements: Partial<Record<DogStat, number>>;
    requirementPercentages: Partial<Record<DogStat, number>>;
};

export type PetSkill = {
    id: number;
    code: string;
    name: string;
    description: string;
    level: number;
    active: boolean;
    activeRequirements: Partial<Record<DogStat, number>>;
    activeRequirementPercentages: Partial<Record<DogStat, number>>;
    levels: SkillLevel[];
    cooldownUntil: string | null;
    canTrain: boolean;
    reason: string | null;
};

export type PetSkills = {
    serverNow: string;
    token: string;
    skills: PetSkill[];
};
