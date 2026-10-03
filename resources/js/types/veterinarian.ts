export type VeterinaryService = 'treatment' | 'checkup' | 'vaccination';

export type VeterinaryOffer = {
    code: VeterinaryService;
    price: number;
    reason: string | null;
    lastVisitAt: string | null;
    availableAt: string | null;
};

export type VeterinaryClinic = {
    token: string;
    selectedPetId: number | null;
    dogs: { id: number; name: string }[];
    health: number | null;
    reason: string | null;
    checkupDays: number;
    checkupHealth: number;
    vaccinationDays: number;
    vaccinationBonus: number;
    services: VeterinaryOffer[];
    diseases: { id: number; name: string; startedAt: string }[];
    history: {
        id: number;
        service: VeterinaryService;
        price: number;
        diseaseName: string | null;
        performedAt: string;
    }[];
};
