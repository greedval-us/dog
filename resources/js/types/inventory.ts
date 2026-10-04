import type { StatusEffect, ItemRisk } from '@/types/pet-care';
import type { EventCompetitionGear } from '@/types/game-event';
export type InventoryItem = {
    id: number;
    name: string;
    category: string;
    categoryCode: string;
    quality: number;
    usageLimit: number;
    remainingUses: number;
    bonuses: Record<string, number>;
    grantedEffects: StatusEffect[];
    risks: ItemRisk[];
    characteristics: Record<string, unknown>;
    acquiredAt: string | null;
    competition?: EventCompetitionGear | null;
};
