export type PetTitleRequirement = {
    key: string;
    label: string;
    current: number;
    target: number;
    met: boolean;
    note: string | null;
};

export type PetTitleCredential = {
    id: number;
    name: string;
    code: string;
    awardedAt: string;
    eventId: number | null;
    status: 'counted' | 'available' | 'used' | 'expired' | 'ineligible';
    reason: string | null;
};

export type PetTitleEvent = {
    id: number;
    name: string;
    startsAt: string;
    closesAt: string;
    classLabel: string;
    contribution: string;
    eligible: boolean;
    reason: string | null;
    registered: boolean;
    petId?: number;
    optionKey?: string;
};

export type PetTitlePath = {
    key: string;
    label: string;
    ready: boolean;
    recommended: boolean;
    requirements: PetTitleRequirement[];
    credentials: PetTitleCredential[];
    events: PetTitleEvent[];
    notes: string[];
};

export type PetTitleGoal = {
    key: string;
    code: string;
    name: string;
    discipline: string;
    scopeLabel: string | null;
    status: 'achieved' | 'ready' | 'in_progress' | 'blocked' | 'unavailable';
    repeatable: boolean;
    earnedCount: number;
    summary: string;
    paths: PetTitlePath[];
};

export type PetTitleProgress = {
    generatedAt: string;
    goals: PetTitleGoal[];
};
