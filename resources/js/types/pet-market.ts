import type { EventTitle } from '@/types/game-event';

export type KennelAgeOption = {
    months: number;
    label: string;
    description: string;
};
export type PetOwnership = {
    listing: { id: number; price: number } | null;
    reason: string | null;
};
export type PetSale = {
    id: number;
    price: number;
    seller: { id: number; username: string };
    pet: {
        id: number;
        name: string;
        breed: string;
        sex: 'male' | 'female';
        bornAt: string;
        ageMonths: number;
        retired: boolean;
        titles: EventTitle[];
    };
};
export type PetMarketPagination = {
    nextCursor: string | null;
    previousCursor: string | null;
};
