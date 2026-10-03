import { describe, expect, it } from 'vite-plus/test';
import { careEnergyCost, careOptionReason } from './petCareAvailability';

describe('care action feedback', () => {
    const option = { baseEnergy: 10, reason: null, reasonCode: null };
    const energyReason = 'Not enough energy. Let your dog rest first.';

    it('allows an action when energy exactly covers the current cost', () => {
        expect(careOptionReason(option, 10, 0)).toBeNull();
    });

    it('explains an energy shortage before the action starts', () => {
        expect(careOptionReason(option, 9, 0)).toBe(energyReason);
    });

    it('removes stale energy feedback when a modifier lowers the cost', () => {
        expect(
            careOptionReason(
                { ...option, reason: 'Energy feedback', reasonCode: 'energy' },
                7,
                -30,
            ),
        ).toBeNull();
    });

    it('updates feedback when an energy discount expires', () => {
        expect(careOptionReason(option, 7, -30)).toBeNull();
        expect(careOptionReason(option, 7, 0)).toBe(energyReason);
    });

    it('preserves a server restriction even when energy is sufficient', () => {
        const reason =
            'Feed your dog and offer water before active play or a walk.';
        expect(
            careOptionReason(
                { ...option, reason, reasonCode: 'active_needs' },
                100,
                -50,
            ),
        ).toBe(reason);
    });

    it('keeps free care available without energy even with a penalty', () => {
        expect(
            careOptionReason(
                { baseEnergy: 0, reason: energyReason, reasonCode: 'energy' },
                0,
                50,
            ),
        ).toBeNull();
    });

    it('rounds costs up so the availability matches the energy charged', () => {
        expect(careEnergyCost(7, 10)).toBe(8);
        expect(careOptionReason({ ...option, baseEnergy: 7 }, 7.5, 10)).toBe(
            energyReason,
        );
    });
});
