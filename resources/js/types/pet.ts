import type { EventExterior, EventTitle } from '@/types/game-event';

export type DogStat =
    | 'endurance'
    | 'speed'
    | 'strength'
    | 'agility'
    | 'obedience'
    | 'intelligence';
export type DogState =
    | 'health'
    | 'energy'
    | 'satiety'
    | 'hydration'
    | 'mood'
    | 'cleanliness'
    | 'bond';
export type DogSize = 'small' | 'medium' | 'large';

export type PetAge = {
    months: number;
    years: number;
    remainingMonths: number;
    stage: 'puppy' | 'junior' | 'adult' | 'senior' | 'retired';
};

export type StarterBreed = {
    id: number;
    name: string;
    description: string;
    size: DogSize;
    illustration: string | null;
    potentials: Record<DogStat, number>;
};

export type PlayerPet = {
    id: number;
    name: string;
    breed: string;
    sex: 'male' | 'female';
    coatColor: string;
    size: DogSize;
    generation: number;
    description: string | null;
    bornAt: string;
    age: PetAge;
    isPurebred: boolean;
    isFavorite: boolean;
    hasPedigree: boolean;
    lifecycle: {
        status: 'active' | 'retired' | 'deceased' | 'surrendered';
        archivedAt: string | null;
        canRetire: boolean;
        retirementEligibleAt: string;
        automaticRetirementAt: string | null;
    };
    traits: string[];
    states: Record<DogState, number>;
    energy: { value: number; maximum: number };
    stats: Record<DogStat, { value: number; potential: number }>;
    exterior?: EventExterior;
    titles?: EventTitle[];
};
