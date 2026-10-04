import type {
    EventDiscipline,
    EventFrequency,
    EventTitle,
} from '@/types/game-event';

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
    rank: number;
    prize: number;
    completedAt: string;
    petName: string;
    eliminated: boolean;
};

export type PetCareer = {
    petId: number;
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
