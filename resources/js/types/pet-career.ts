import type {
    EventDiscipline,
    EventFrequency,
    EventTitle,
} from '@/types/game-event';
import type { PetTitleProgress } from '@/types/pet-title-progress';

export type PetCareerTitle = EventTitle & {
    count: number;
    eventId: number | null;
};

export type PetCareerResult = {
    id: number;
    eventId: number;
    kind: 'competition' | 'exhibition';
    discipline: EventDiscipline;
    frequency: EventFrequency;
    division: string;
    divisionLabel: string;
    rank: number | null;
    prize: number;
    completedAt: string;
    petName: string;
    eliminated: boolean;
};

export type PetCareer = {
    petId: number;
    titleProgress?: PetTitleProgress;
    titles: PetCareerTitle[];
    summary: {
        competitionStarts: number;
        competitionWins: number;
        exhibitionStarts: number;
        exhibitionWins: number;
        podiums: number;
        cups: number;
    };
    results: {
        entries: PetCareerResult[];
        nextCursor: string | null;
        previousCursor: string | null;
    };
};
