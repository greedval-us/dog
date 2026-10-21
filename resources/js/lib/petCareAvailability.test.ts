import { describe, expect, it } from 'vite-plus/test';
import {
    careEnergyCost,
    careGroupReason,
    careOptionReason,
} from './petCareAvailability';
import type { CareOption } from '@/types/pet-care';

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

describe('shared care group availability', () => {
    const option: CareOption = {
        id: 'water',
        group: 'feed',
        label: 'Water',
        duration: 5,
        cooldown: 5,
        energy: 0,
        baseEnergy: 0,
        grantedEffects: [],
        statusRecovery: {},
        optional: [],
        requirements: [],
        uses: {},
        effects: {},
        reason: null,
        reasonCode: null,
        gainsByQuality: {},
    };
    const context = {
        care: {
            blocked: false,
            busy: false,
            working: false,
            cooldowns: {},
            options: [option],
        },
        group: 'feed' as const,
        now: Date.parse('2026-10-20T12:00:00Z'),
        energy: 0,
        modifier: 0,
        busyMessage: 'Applying the activity result...',
    };
    const t = (key: string, replacements?: Record<string, string | number>) =>
        key.replace('{time}', String(replacements?.time ?? ''));

    it('gives account restrictions precedence over work and current activity', () => {
        expect(
            careGroupReason(
                {
                    ...context,
                    care: {
                        ...context.care,
                        blocked: true,
                        busy: true,
                        working: true,
                    },
                },
                t,
            ),
        ).toBe('Care is unavailable for this dog or account.');
        expect(
            careGroupReason(
                {
                    ...context,
                    care: { ...context.care, busy: true, working: true },
                },
                t,
            ),
        ).toBe('Your dog is working.');
        expect(
            careGroupReason(
                { ...context, care: { ...context.care, busy: true } },
                t,
            ),
        ).toBe('Applying the activity result...');
    });

    it('unlocks a group exactly at its short cooldown boundary', () => {
        const care = {
            ...context.care,
            cooldowns: { feed: '2026-10-20T12:00:10Z' },
        };
        expect(careGroupReason({ ...context, care }, t)).toBe(
            'Available in 0:10',
        );
        expect(
            careGroupReason({ ...context, care, now: context.now + 10_000 }, t),
        ).toBeNull();
    });

    it('keeps a group available when one variant is affordable and checks a selected variant independently', () => {
        const costly = { ...option, id: 'food', baseEnergy: 10 };
        const care = { ...context.care, options: [costly, option] };
        expect(careGroupReason({ ...context, care }, t)).toBeNull();
        expect(careGroupReason({ ...context, care, option: costly }, t)).toBe(
            'Not enough energy. Let your dog rest first.',
        );
    });
});
