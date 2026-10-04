import type { StatusEffect, ItemRisk } from '@/types/pet-care';
import type { CompetitionGear } from '@/types/competition-gear';
export type ShopCategory = {
    id: number;
    code: string;
    name: string;
};

export type ShopOffer = {
    id: number;
    itemId: number;
    name: string;
    description: string;
    category: string;
    categoryCode: string;
    quality: number;
    usageLimit: number;
    bonuses: Record<string, number>;
    grantedEffects: StatusEffect[];
    risks: ItemRisk[];
    characteristics: Record<string, unknown>;
    currency: 'coins';
    price: number;
    stock: number | null;
    owned: number;
    competition?: CompetitionGear | null;
    nextRestockAt?: string | null;
    purchaseLimit?: number | null;
    purchasedThisPeriod?: number;
    soldOut?: boolean;
};
