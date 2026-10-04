import type { CompetitionGear } from '@/types/competition-gear';

export type EventDiscipline =
    | 'agility'
    | 'nosework'
    | 'canicross'
    | 'conformation'
    | 'progeny';

export type EventFrequency = 'daily' | 'weekly' | 'monthly';
export type GameEventFilters = {
    frequency: EventFrequency | null;
    kind: 'competition' | 'exhibition' | null;
};
export type EventDecision = 'careful' | 'balanced' | 'bold';
export type EventExterior = {
    type: number;
    structure: number;
    movement: number;
};
export type EventTitle = {
    name: string;
    discipline: EventDiscipline;
    frequency: EventFrequency;
    awardedAt: string;
};

export type EventDog = {
    id: number;
    name: string;
    breed: string;
    size: string;
    isActive: boolean;
    isBusy: boolean;
    exterior: EventExterior;
    titles: EventTitle[];
    offspring: {
        id: number;
        name: string;
        exterior: EventExterior;
        titlesCount: number;
    }[];
};

export type EventEquipment = CompetitionGear & {
    id: number;
    name: string;
    remainingUses: number;
};

export type GameEventSummary = {
    id: number;
    discipline: EventDiscipline;
    frequency: EventFrequency;
    status: 'scheduled' | 'locked' | 'completed' | 'cancelled';
    startsAt: string;
    closesAt: string;
    opensAt: string;
    fee: number;
    prizes: number[];
    entryCount: number;
    canRegister: boolean;
    stages: { key: string; label: string; options: string[] }[];
};

export type GameEventPlan = {
    stages: EventDecision[];
    offspring_ids?: number[];
};

export type GameEventEntryProps = {
    event: GameEventDetail;
    serverNow: string;
    dogs: EventDog[];
    equipment: EventEquipment[];
    entry: GameEventEntry | null;
};

export type GameEventEntry = {
    id: number;
    petId: number | null;
    name: string;
    ownerName: string | null;
    isNpc: boolean;
    division: string;
    divisionLabel?: string;
    status: string;
    plan: GameEventPlan;
    gearIds: number[];
    rank: number | null;
    prize: number;
    result: {
        time: number;
        penalties: number;
        score: number;
        eliminated: boolean;
        reason?: string;
        stages: {
            key: string;
            decision: EventDecision;
            time: number;
            penalties: number;
            score: number;
            fatigue: number;
            focus: number;
            reason: string;
        }[];
    } | null;
};

export type GameEventDetail = GameEventSummary & {
    entries: GameEventEntry[];
    ownEntry?: GameEventEntry | null;
};
