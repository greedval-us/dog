import type { CareOption } from '@/types/pet-care';

export function careEnergyCost(baseEnergy: number, modifier: number): number {
    return baseEnergy === 0
        ? 0
        : Math.max(1, Math.ceil((baseEnergy * (100 + modifier)) / 100));
}

export function careOptionReason(
    option: Pick<CareOption, 'baseEnergy' | 'reason' | 'reasonCode'>,
    energy: number,
    modifier: number,
): string | null {
    const energyReason = 'Not enough energy. Let your dog rest first.';
    if (option.reasonCode !== null && option.reasonCode !== 'energy') {
        return option.reason;
    }

    return energy < careEnergyCost(option.baseEnergy, modifier)
        ? energyReason
        : null;
}
