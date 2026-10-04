import type { StatusEffect, ItemRisk } from '@/types/pet-care';
import type { EventCompetitionGear } from '@/types/game-event';
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
    competition?: EventCompetitionGear | null;
    nextRestockAt?: string | null;
    purchaseLimit?: number | null;
    purchasedThisPeriod?: number;
    soldOut?: boolean;
};
