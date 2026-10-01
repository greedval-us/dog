export type DogWorkOffer = {
    id: number;
    name: string;
    description: string;
    skill: string;
    skillLevel: number;
    dogSkillLevel: number;
    skillActive: boolean;
    coins: number;
    gems: number;
    duration: number;
    energy: number;
    places: number;
    limit: number;
    reason: string | null;
    status: 'started' | 'completed' | null;
};

export type DogWorkBoard = {
    token: string;
    serverNow: string;
    date: string;
    resetsAt: string;
    timezone: string;
    selectedPetId: number | null;
    dogs: { id: number; name: string; busy: boolean; retired: boolean }[];
    offers: DogWorkOffer[];
    shifts: {
        token: string;
        petId: number;
        petName: string;
        name: string;
        endsAt: string;
        coins: number;
        gems: number;
    }[];
};
