import type { StatusEffect, ItemRisk } from '@/types/pet-care';
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
    characteristics: Record<string, number | string | boolean>;
    acquiredAt: string | null;
};
