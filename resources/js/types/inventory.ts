import type { StatusEffect } from '@/types/pet-care';
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
    characteristics: Record<string, number | string | boolean>;
    acquiredAt: string | null;
};
