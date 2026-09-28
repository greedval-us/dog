import type { StatusEffect } from '@/types/pet-care';
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
    characteristics: Record<string, number | string | boolean>;
    currency: 'coins';
    price: number;
    stock: number | null;
    owned: number;
};
