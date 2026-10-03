export type PetHistoryKind = 'action' | 'thought';
export type PetHistoryPeriod = 7 | 30;
export type PetHistoryUnit = 'percent' | 'points' | 'coins' | 'gems';

export type PetHistoryChange = {
    metric: string;
    label?: string;
    before: number;
    after: number;
    delta: number;
    unit: PetHistoryUnit;
};

export type PetHistoryEntry = {
    id: number;
    kind: PetHistoryKind;
    eventCode: string;
    title: string;
    message: string | null;
    occurredAt: string;
    details: {
        stage?: 'started' | 'completed';
        name?: string;
        diseaseName?: string | null;
        coins?: number;
        gems?: number;
        durationSeconds?: number;
        changes?: PetHistoryChange[];
    };
};

export type PetHistoryPage = {
    data: PetHistoryEntry[];
    nextCursor: string | null;
    previousCursor: string | null;
    events: { code: string; name: string }[];
    kind: PetHistoryKind;
    period: PetHistoryPeriod;
};
