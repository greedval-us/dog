import type { PlayerDog } from '@/types/player';

export type MemorialPet = PlayerDog & {
    bornAt: string;
    archivedAt: string;
    status: 'deceased';
};

export type PetMemorialList = {
    data: MemorialPet[];
    previousCursor: string | null;
    nextCursor: string | null;
};
