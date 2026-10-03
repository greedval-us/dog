import { describe, expect, it } from 'vite-plus/test';
import { careModifiers, petCarePreview } from './petCarePreview';
import type { PlayerPet } from '@/types/pet';
import type {
    CareItem,
    CareOption,
    PetCare,
    StatusEffect,
} from '@/types/pet-care';

const now = Date.parse('2026-10-03T10:00:00Z');
function effect(attributes: Partial<StatusEffect> = {}): StatusEffect {
    return {
        code: 'calm',
        kind: 'buff',
        name: { en: 'Calm' },
        description: {},
        modifiers: {},
        duration_seconds: 120,
        condition_state: null,
        ...attributes,
    };
}
function option(attributes: Partial<CareOption> = {}): CareOption {
    return {
        id: 'nap',
        group: 'sleep',
        label: 'Take a nap',
        duration: 60,
        cooldown: 60,
        energy: 10,
        baseEnergy: 10,
        grantedEffects: [],
        statusRecovery: {},
        optional: [],
        requirements: [],
        uses: {},
        effects: {},
        reason: null,
        reasonCode: null,
        gainsByQuality: {},
        ...attributes,
    };
}
function item(attributes: Partial<CareItem> = {}): CareItem {
    return {
        id: 1,
        category: 'food',
        name: 'Food',
        quality: 5,
        bonuses: {},
        grantedEffects: [],
        risks: [],
        bonus: {},
        remainingUses: 10,
        ...attributes,
    };
}
function pet(): Pick<PlayerPet, 'energy' | 'states'> {
    return {
        energy: { value: 80, maximum: 200 },
        states: {
            health: 90,
            energy: 40,
            satiety: 70,
            hydration: 5,
            mood: 50,
            cleanliness: 80,
            bond: 40,
        },
    };
}
function care(
    attributes: Partial<PetCare> = {},
): Pick<PetCare, 'modifierKeys' | 'buffs' | 'debuffs'> {
    return { modifierKeys: [], buffs: [], debuffs: [], ...attributes };
}

describe('care result preview', () => {
    it('excludes expired modifiers while retaining untimed effects and caps combined modifiers', () => {
        const status = care({
            modifierKeys: ['energy_cost_percent', 'mood_gain_percent'],
            buffs: [
                effect({
                    modifiers: { energy_cost_percent: -80 },
                    expires_at: null,
                }),
            ],
            debuffs: [
                effect({
                    modifiers: { mood_gain_percent: -90 },
                    expires_at: now / 1000 + 1,
                }),
                effect({
                    modifiers: { energy_cost_percent: 50 },
                    expires_at: now / 1000,
                }),
            ],
        });

        expect(careModifiers(status, now)).toEqual({
            energy_cost_percent: -50,
            mood_gain_percent: -50,
        });
    });

    it('caps aggregate item bonuses before applying gain and loss modifiers and need limits', () => {
        const status = care({
            modifierKeys: ['mood_gain_percent', 'hydration_loss_percent'],
            buffs: [
                effect({
                    modifiers: {
                        mood_gain_percent: 20,
                        hydration_loss_percent: -50,
                    },
                }),
            ],
        });
        const preview = petCarePreview(
            option({ effects: { mood: 10, hydration: -20, health: 30 } }),
            [
                item({ bonus: { mood: 5 }, bonuses: { mood: 25 } }),
                item({ id: 2, bonuses: { mood: 20, health: -10 } }),
            ],
            pet(),
            status,
            now,
        );

        expect(preview.stateEffects).toEqual([
            { state: 'mood', amount: 50 },
            { state: 'hydration', amount: -5 },
            { state: 'health', amount: 10 },
        ]);
    });

    it('previews energy gained after its immediate cost with the actual energy maximum', () => {
        const preview = petCarePreview(
            option({ effects: { energy: 80 } }),
            [],
            pet(),
            care(),
            now,
        );

        expect(preview.energyCostPercentage).toBe(5);
        expect(preview.stateEffects).toEqual([{ state: 'energy', amount: 65 }]);
        expect(preview.energyGainCapped).toBe(true);
    });

    it('uses the selected equipment quality for training gains', () => {
        const preview = petCarePreview(
            option({
                group: 'training',
                gainsByQuality: { 5: { strength: 3 }, 10: { strength: 7 } },
            }),
            [item({ category: 'sports', quality: 5 })],
            pet(),
            care(),
            now,
        );

        expect(preview.statGains).toEqual({ strength: 3 });
    });

    it('shows the longest guaranteed effect and the highest risk with lifetime breaking ties', () => {
        const calm = effect();
        const risk = (chance: number, seconds: number) => ({
            effect: effect({
                code: 'strain',
                kind: 'debuff',
                duration_seconds: seconds,
            }),
            chance,
            item_name: {},
            quality: 5,
        });
        const preview = petCarePreview(
            option({ grantedEffects: [calm], risks: [risk(1000, 600)] }),
            [
                item({
                    grantedEffects: [effect({ duration_seconds: 300 })],
                    risks: [
                        risk(2000, 60),
                        {
                            effect: calm,
                            chance: 3000,
                            item_name: {},
                            quality: 5,
                        },
                    ],
                }),
                item({ id: 2, risks: [risk(2000, 180)] }),
            ],
            pet(),
            care(),
            now,
        );

        expect(preview.grantedEffects).toEqual([
            effect({ duration_seconds: 300 }),
        ]);
        expect(preview.risks).toEqual([risk(2000, 180)]);
    });

    it('shows recovery only for a live timed condition treated by the chosen option', () => {
        const live = effect({ kind: 'debuff', expires_at: now / 1000 + 120 });
        const status = care({
            debuffs: [
                live,
                effect({
                    code: 'expired',
                    kind: 'debuff',
                    expires_at: now / 1000,
                }),
                effect({
                    code: 'disease',
                    kind: 'debuff',
                    disease_id: 1,
                    expires_at: null,
                }),
            ],
        });
        const preview = petCarePreview(
            option({ statusRecovery: { calm: 60, expired: 60, disease: 60 } }),
            [],
            pet(),
            status,
            now,
        );

        expect(preview.recoveryEffects).toEqual([live]);
    });
});
