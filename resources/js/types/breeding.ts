import type { DogStat } from '@/types/pet';

export type BreedingParent = {
    id: number;
    name: string;
    sex: 'male' | 'female';
    breed: string;
    breedName: string;
    coatColor: string;
    coatColorLabel: string;
    generation: number;
    stats: Record<DogStat, { value: number; potential: number }>;
    reason: string | null;
    cooldownUntil: string | null;
};

export type BreedingForecast = {
    ownPair?: boolean;
    father: BreedingParent;
    mother: BreedingParent;
    ranges: { stat: DogStat; min: number; max: number }[];
    colors: { code: string; label: string; chance: number; rare: boolean }[];
    reason: string | null;
};

export type Puppy = {
    id: number;
    name: string;
    breed: string;
    breedCode: string;
    illustration: string | null;
    sex: 'male' | 'female';
    coatColor: string;
    coatLabel: string;
    generation: number;
    potentials: Record<DogStat, number>;
    status: 'pending' | 'listed' | 'kennel';
    price: number | null;
    expiresAt: string;
    seller: { name: string; username: string } | null;
};

export type PuppyPagination = {
    nextCursor: string | null;
    previousCursor: string | null;
};
