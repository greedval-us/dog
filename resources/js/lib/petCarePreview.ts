import { careEnergyCost } from '@/lib/petCareAvailability';
import type { DogState, PlayerPet } from '@/types/pet';
import type {
    CareItem,
    CareModifier,
    CareModifiers,
    CareOption,
    ItemRisk,
    PetCare,
    StatusEffect,
} from '@/types/pet-care';

export function careModifiers(
    care: Pick<PetCare, 'modifierKeys' | 'buffs' | 'debuffs'>,
    now: number,
): CareModifiers {
    const result: CareModifiers = {};
    for (const key of care.modifierKeys) result[key] = 0;
    for (const effect of [...care.buffs, ...care.debuffs]) {
        if (effect.expires_at && effect.expires_at * 1000 <= now) continue;
        for (const key of care.modifierKeys) {
            result[key] = (result[key] ?? 0) + (effect.modifiers[key] ?? 0);
        }
    }
    for (const key of care.modifierKeys) {
        result[key] = Math.max(-50, Math.min(50, result[key] ?? 0));
    }
    return result;
}

export function petCarePreview(
    option: CareOption,
    items: CareItem[],
    pet: Pick<PlayerPet, 'states' | 'energy'>,
    care: Pick<PetCare, 'modifierKeys' | 'buffs' | 'debuffs'>,
    now: number,
) {
    const modifiers = careModifiers(care, now);
    const energyCost = careEnergyCost(
        option.baseEnergy,
        modifiers.energy_cost_percent ?? 0,
    );
    const energyCostPercentage =
        pet.energy.maximum > 0 ? (energyCost / pet.energy.maximum) * 100 : 0;
    const energyAfterCost = Math.max(
        0,
        pet.states.energy - energyCostPercentage,
    );
    const effects = { ...option.effects };
    const itemBonuses: Partial<Record<DogState, number>> = {};
    for (const item of items) {
        for (const [state, bonus] of Object.entries(item.bonus)) {
            const key = state as DogState;
            effects[key] = (effects[key] ?? 0) + bonus;
        }
        for (const [state, bonus] of Object.entries(item.bonuses)) {
            const key = state as DogState;
            itemBonuses[key] = (itemBonuses[key] ?? 0) + bonus;
        }
    }
    for (const [state, bonus] of Object.entries(itemBonuses)) {
        const key = state as DogState;
        effects[key] = (effects[key] ?? 0) + Math.max(0, Math.min(30, bonus));
    }
    const stateEffects = Object.entries(effects).map(([state, amount]) => {
        const key = state as DogState;
        const modifierKey: CareModifier = `${key}_${amount >= 0 ? 'gain' : 'loss'}_percent`;
        const adjusted =
            Math.round(
                ((amount * (100 + (modifiers[modifierKey] ?? 0))) / 100) *
                    10000,
            ) / 10000;
        const current = state === 'energy' ? energyAfterCost : pet.states[key];
        return {
            state: key,
            amount:
                Math.round(
                    Math.max(-current, Math.min(100 - current, adjusted)) * 10,
                ) / 10,
        };
    });
    const equipment = items.find((item) => item.category === 'sports');
    const statGains = equipment
        ? (option.gainsByQuality[equipment.quality] ?? {})
        : {};
    const granted = new Map<string, StatusEffect>(
        option.grantedEffects.map((effect) => [effect.code, effect]),
    );
    for (const item of items) {
        for (const effect of item.grantedEffects) {
            if (
                (effect.duration_seconds ?? 0) >
                (granted.get(effect.code)?.duration_seconds ?? 0)
            )
                granted.set(effect.code, effect);
        }
    }
    const risks = new Map<string, ItemRisk>(
        (option.risks ?? []).map((risk) => [risk.effect.code, risk]),
    );
    for (const item of items) {
        for (const risk of item.risks) {
            const previous = risks.get(risk.effect.code);
            if (
                !granted.has(risk.effect.code) &&
                (risk.chance > (previous?.chance ?? 0) ||
                    (risk.chance === previous?.chance &&
                        (risk.effect.duration_seconds ?? 0) >
                            (previous.effect.duration_seconds ?? 0)))
            )
                risks.set(risk.effect.code, risk);
        }
    }

    return {
        stateEffects,
        statGains,
        energyCostPercentage,
        energyGainCapped: (option.effects.energy ?? 0) > 100 - energyAfterCost,
        grantedEffects: [...granted.values()],
        risks: [...risks.values()],
        recoveryEffects: care.debuffs.filter(
            (effect) =>
                (effect.expires_at ?? 0) * 1000 > now &&
                (option.statusRecovery[effect.code] ?? 0) > 0,
        ),
    };
}
