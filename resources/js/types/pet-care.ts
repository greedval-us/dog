import type { DogState, DogStat } from '@/types/pet';

export type CareCategory =
    | 'food'
    | 'collars'
    | 'leashes'
    | 'toys'
    | 'care'
    | 'clothing'
    | 'sports';
export type CareItemSelection = Partial<Record<CareCategory, number>>;
export type CareModifier =
    | 'energy_cost_percent'
    | 'stats_decay_percent'
    | `${DogState}_gain_percent`
    | `${DogState}_loss_percent`
    | `${Exclude<DogState, 'health' | 'energy'> | DogStat}_decay_percent`;
export type CareModifiers = Partial<Record<CareModifier, number>>;
export type CareRefusal =
    | 'training_needs'
    | 'energy'
    | 'active_needs'
    | 'need_full'
    | 'training_potential'
    | 'player_blocked'
    | 'inactive'
    | 'busy'
    | 'cooldown'
    | 'items_required'
    | 'item_unavailable'
    | 'not_ready';

export type StatusEffect = {
    code: string;
    kind: 'buff' | 'debuff';
    name: Record<string, string>;
    description: Record<string, string>;
    modifiers: CareModifiers;
    duration_seconds: number | null;
    condition_state: DogState | null;
    condition_threshold?: number | null;
    condition_operator?: 'lt' | 'lte' | 'gt' | 'gte';
    expires_at?: number | null;
    recovery_actions?: Record<string, number>;
    disease_id?: number;
    starts_at?: number;
    conditions?: {
        state: DogState;
        operator: 'lt' | 'lte' | 'gt' | 'gte';
        threshold: number;
    }[];
    condition_group?: string | null;
    condition_priority?: number;
    care_variants?: string[];
};

export type ItemRisk = {
    effect: StatusEffect;
    chance: number;
    item_name: Record<string, string>;
    quality: number;
};

export type CareGroup =
    | 'feed'
    | 'walk'
    | 'play'
    | 'groom'
    | 'sleep'
    | 'training';
export type CareItem = {
    id: number;
    category: CareCategory;
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
    optional: CareCategory[];
    requirements: CareCategory[];
    uses: Partial<Record<CareCategory, number>>;
    effects: Partial<Record<DogState, number>>;
    reason: string | null;
    reasonCode: CareRefusal | null;
    statGains?: Partial<Record<DogStat, number>>;
    trainingName?: Record<string, string>;
    gainsByQuality: Record<number, Partial<Record<DogStat, number>>>;
    risks?: ItemRisk[];
};
export type PetCare = {
    token: string;
    modifierKeys: CareModifier[];
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
    working: boolean;
    cooldowns: Partial<Record<CareGroup, string>>;
    options: CareOption[];
    active: null | {
        token: string;
        label: string;
        startedAt: string;
        endsAt: string;
        effects: Partial<Record<DogState, number>>;
        statGains: Partial<Record<DogStat, number>> | null;
    };
};
