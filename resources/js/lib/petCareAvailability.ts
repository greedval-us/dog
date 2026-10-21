import type { CareGroup, CareOption, PetCare } from '@/types/pet-care';

type CareAvailabilityContext = {
    care: Pick<
        PetCare,
        'blocked' | 'working' | 'busy' | 'cooldowns' | 'options'
    >;
    group: CareGroup;
    now: number;
    energy: number;
    modifier: number;
    busyMessage: string;
    option?: CareOption;
};

export function careGroupReason(
    context: CareAvailabilityContext,
    t: (key: string, replacements?: Record<string, string | number>) => string,
): string | null {
    const { care, group, now, energy, modifier, busyMessage, option } = context;
    if (care.blocked) return t('Care is unavailable for this dog or account.');
    if (care.working) return t('Your dog is working.');
    if (care.busy) return t(busyMessage);
    const remaining = Math.max(
        0,
        Math.ceil((Date.parse(care.cooldowns[group] ?? '') - now) / 1000),
    );
    if (remaining) {
        return t('Available in {time}', {
            time: `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`,
        });
    }
    const options = option
        ? [option]
        : care.options.filter((item) => item.group === group);
    if (!options.length) return t('No options are available yet.');
    const reasons = options.map((item) =>
        careOptionReason(item, energy, modifier),
    );
    return reasons.every((reason) => reason !== null) ? t(reasons[0]!) : null;
}

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
