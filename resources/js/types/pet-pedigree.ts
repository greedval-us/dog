import type { DogSize, DogStat } from '@/types/pet';
import type { EventExterior, EventTitle } from '@/types/game-event';

export type PetLifecycleStatus = 'active' | 'retired' | 'deceased';

export type PetPublicProfile = {
    id: number;
    name: string;
    breed: string;
    sex: 'male' | 'female';
    size: DogSize;
    coatColor: string;
    generation: number;
    description: string | null;
    bornAt: string;
    isPurebred: boolean;
    traits: string[];
    stats: Record<DogStat, { value: number; potential: number }>;
    lifecycle: {
        status: PetLifecycleStatus;
        archivedAt: string | null;
    };
    hasPedigree: boolean;
    exterior?: EventExterior;
    titles?: EventTitle[];
};

export type PedigreeNode = {
    pet: Pick<
        PetPublicProfile,
        | 'id'
        | 'name'
        | 'breed'
        | 'sex'
        | 'coatColor'
        | 'generation'
        | 'hasPedigree'
    > & {
        status: PetLifecycleStatus;
        exterior?: EventExterior;
        titles?: EventTitle[];
    };
    father: PedigreeNode | null;
    mother: PedigreeNode | null;
};

export type PetPedigree = {
    root: PedigreeNode;
    hasAncestors: boolean;
    generations: number;
};
