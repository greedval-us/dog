import type { DogSize, DogStat, DogState } from '@/types/pet';

export const statLabels: Record<DogStat, string> = {
    endurance: 'Endurance',
    speed: 'Speed',
    strength: 'Strength',
    agility: 'Agility',
    obedience: 'Obedience',
    intelligence: 'Intelligence',
};
export const stateLabels: Record<DogState, string> = {
    health: 'Health',
    energy: 'Energy',
    satiety: 'Satiety',
    hydration: 'Hydration',
    mood: 'Mood',
    cleanliness: 'Cleanliness',
    bond: 'Bond',
};
export const sizeLabels: Record<DogSize, string> = {
    small: 'Small breed',
    medium: 'Medium breed',
    large: 'Large breed',
};
