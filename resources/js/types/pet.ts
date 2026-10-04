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
    isPurebred: boolean;
    isFavorite: boolean;
    hasPedigree: boolean;
    lifecycle: {
        status: 'active' | 'retired' | 'deceased';
        archivedAt: string | null;
        canRetire: boolean;
        retirementEligibleAt: string;
        automaticRetirementAt: string;
    };
    traits: string[];
    states: Record<DogState, number>;
    energy: { value: number; maximum: number };
    stats: Record<DogStat, { value: number; potential: number }>;
    exterior?: EventExterior;
    titles?: EventTitle[];
};
