import type { DogState } from '@/types/pet';

export type CareGroup = 'feed' | 'walk' | 'play' | 'groom' | 'sleep';
export type CareItem = {
    id: number;
    category: string;
    name: string;
    quality: number;
    remainingUses: number;
};
export type CareOption = {
    id: string;
    group: CareGroup;
    label: string;
    description: string;
    duration: number;
    cooldown: number;
    energy: number;
    requirements: string[];
    effects: Partial<Record<DogState, number>>;
    reason: string | null;
};
export type PetCare = {
    token: string;
    serverNow: string;
    blocked: boolean;
    busy: boolean;
    cooldowns: Partial<Record<CareGroup, string>>;
    options: CareOption[];
    items: CareItem[];
    active: null | {
        token: string;
        label: string;
        endsAt: string;
        effects: Partial<Record<DogState, number>>;
    };
};
