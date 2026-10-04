export type CompetitionGear = {
    slot: string;
    disciplines: string[];
    phase: string;
    sizes: string[];
    modifiers: Record<string, number>;
    description?: string | Record<string, string>;
};
