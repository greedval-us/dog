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
export type ExhibitionClass =
    | 'baby'
    | 'puppy'
    | 'junior'
    | 'intermediate'
    | 'open'
    | 'working'
    | 'champion'
    | 'club_champion'
    | 'veteran';
export type EventAwardKind =
    | 'legacy'
    | 'placement'
    | 'certificate'
    | 'comparison'
    | 'championship'
    | 'qualification';
export type EventRegulation = {
    version: number;
    system: 'rkf' | 'fci';
    rank: string;
    rankLabel: string;
    country: string;
    region: string;
    regionLabel?: string | null;
    judgeId: string;
    judgeName: string;
    format: string;
    formatLabel?: string;
    sourceUrl?: string;
    sources?: { url: string; label?: string }[];
    specialShow?: string;
    specialShowLabel?: string | null;
    showLeg?: string;
    showLegLabel?: string | null;
    showYear?: number;
    showEdition?: string;
    sportScope?: 'general' | 'breed';
    sportScopeLabel?: string | null;
    minimumField?: number;
    policyNote?: string | null;
};
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
    code?: string;
    kind?: EventAwardKind;
    context?: Record<string, unknown>;
    details?: Record<string, unknown>;
    scopeKey?: string;
    sourceCount?: number;
    regulationVersion?: string | null;
    classKey?: string | null;
    rank?: number | null;
    ageMonths?: number | null;
    breedId?: number | null;
};

export type EventModifier = 'precision' | 'stamina' | 'pace' | 'focus';
export type EventPreparationSnapshot = {
    careMultiplier: number;
    initialFatigue: number;
    initialFocus: number;
    careerExperience?: number;
    consistencyBonus?: number;
    states: Record<string, number>;
    normalizedStats: Record<string, number>;
    modifiers: Record<EventModifier, number>;
};
export type EventPreparation = EventPreparationSnapshot & {
    stats: Record<string, number>;
    potentials: Record<string, number>;
    skills: Record<string, number>;
    stages: { key: string; weights: Record<string, number>; quality: number }[];
    pedigree: { generation: number; knownParents: number };
    divisionLabel: string;
    blockingReasons: string[];
};

export type EventDog = {
    id: number;
    name: string;
    breed: string;
    size: string;
    isActive: boolean;
    isBusy: boolean;
    ageMonths: number;
    availableClasses: { key: ExhibitionClass; label: string }[];
    defaultClass: ExhibitionClass | null;
    entryOptions?: EventEntryOption[];
    defaultEntryOption?: string | null;
    profile?: { heightCm: number | null; heightCategory: string | null };
    exterior: EventExterior;
    titles: EventTitle[];
    offspring: {
        id: number;
        name: string;
        exterior: EventExterior;
        titlesCount: number;
    }[];
    preparation?: EventPreparation;
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
    calculationVersion: number;
    regulation?: EventRegulation | null;
    fieldSize: number;
    humanCount: number;
    clubCount: number;
    participationRules: {
        playerDailyLimit: number;
        petDailyLimit: number;
        petRestHours: number;
    };
    canRegister: boolean;
    stages: { key: string; label: string; options: string[] }[];
};

export type GameEventPlan = {
    stages: EventDecision[];
    offspring_ids?: number[];
    exhibition_class?: string;
    sport_class?: string;
    program?: string;
    test_type?: string;
    handler_category?: string;
};
export type EventEntryOption = {
    key: string;
    label: string;
    plan: Omit<GameEventPlan, 'stages' | 'offspring_ids'>;
    categoryLabel?: string;
    eligible?: boolean;
    reason?: string | null;
};
export type EventAssessment = {
    code: string;
    label: string;
    qualified: boolean;
};
export type EventStanding = {
    key: string;
    label: string;
    rank: number | null;
    score: number;
    time: number;
    penalties: number;
    qualified: boolean;
};

export type GameEventEntryProps = {
    event: GameEventDetail;
    serverNow: string;
    dogs: EventDog[];
    equipment: EventEquipment[];
    entry: GameEventEntry | null;
    selectedPetId?: number | null;
    selectedEntryOptionKey?: string | null;
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
    awards?: EventTitle[];
    ageMonths?: number | null;
    result: {
        time: number;
        penalties: number;
        score: number;
        eliminated: boolean;
        reason?: string;
        assessment?: EventAssessment;
        standings?: EventStanding[];
        preparation?: EventPreparationSnapshot;
        stages: {
            key: string;
            decision: EventDecision;
            time: number;
            penalties: number;
            score: number;
            fatigue: number;
            focus: number;
            reason: string;
            factors?: {
                quality: number;
                careMultiplier: number;
                mistakeChance: number;
                startFatigue: number;
                startFocus: number;
                exterior: number | null;
                exteriorContribution: number;
                presentationContribution: number;
                consistencyBonus?: number;
            };
        }[];
    } | null;
};

export type GameEventDetail = GameEventSummary & {
    entries: GameEventEntry[];
    ownEntry?: GameEventEntry | null;
    divisions: {
        key: string;
        label: string;
        humanCount: number;
        clubCount: number;
    }[];
    activeDivision: string | null;
};
