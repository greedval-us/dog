import type { StatusEffect, ItemRisk } from '@/types/pet-care';
import type { CompetitionGear } from '@/types/competition-gear';
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
    competition?: CompetitionGear | null;
};
