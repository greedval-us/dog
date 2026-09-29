import type { DogState } from '@/types/pet';

export type StatusEffect = {
    code: string;
    kind: 'buff' | 'debuff';
    name: Record<string, string>;
    description: Record<string, string>;
    modifiers: Record<string, number>;
    duration_seconds: number | null;
    condition_state: DogState | null;
    condition_threshold?: number | null;
    condition_operator?: 'lt' | 'lte' | 'gt' | 'gte';
    expires_at?: number | null;
    recovery_actions?: Record<string, number>;
};

export type ItemRisk = {
    effect: StatusEffect;
    chance: number;
    item_name: Record<string, string>;
    quality: number;
};

export type CareGroup = 'feed' | 'walk' | 'play' | 'groom' | 'sleep';
export type CareItem = {
    id: number;
    category: string;
    name: string;
    quality: number;
    bonuses: Partial<Record<DogState, number>>;
    grantedEffects: StatusEffect[];
    risks: ItemRisk[];
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
    baseEnergy: number;
    grantedEffects: StatusEffect[];
    statusRecovery: Record<string, number>;
    optional: string[];
    requirements: string[];
    uses: Record<string, number>;
    effects: Partial<Record<DogState, number>>;
    reason: string | null;
};
export type PetCare = {
    token: string;
    modifierKeys: string[];
    buffs: StatusEffect[];
    debuffs: StatusEffect[];
    recentIncidents: {
        id: number;
        occurredAt: string;
        incidents: ItemRisk[];
    }[];
    serverNow: string;
    blocked: boolean;
    busy: boolean;
    cooldowns: Partial<Record<CareGroup, string>>;
    options: CareOption[];
    active: null | {
        token: string;
        label: string;
        startedAt: string;
        endsAt: string;
        effects: Partial<Record<DogState, number>>;
    };
};
