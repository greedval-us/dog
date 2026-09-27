import type { DogState } from '@/types/pet';

export type CareGroup = 'feed' | 'walk' | 'play' | 'groom' | 'sleep';
export type CareItem = {
    id: number;
    category: string;
    name: string;
    quality: number;
    bonus: Partial<Record<DogState, number>>;
    remainingUses: number;
};
export type CareOption = {
    id: string;
    group: CareGroup;
    label: string;
    duration: number;
    cooldown: number;
    energy: number;
    requirements: string[];
    uses: Record<string, number>;
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
        startedAt: string;
        endsAt: string;
        effects: Partial<Record<DogState, number>>;
    };
};
