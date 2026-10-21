import { expect, it } from 'vite-plus/test';
import { groupEventAwards } from './eventAwards';
import type { EventAwardKind, EventTitle } from '@/types/game-event';

function award(code: string | undefined, kind: EventAwardKind): EventTitle {
    return {
        code,
        kind,
        name: 'Award',
        discipline: 'progeny',
        frequency: 'daily',
        awardedAt: '2026-10-21T00:00:00Z',
    };
}

it.each([
    [
        'group final places',
        'placement',
        ['PROGENY_PLACE_1', 'PROGENY_PLACE_3'],
        'Group final placements',
    ],
    [
        'group comparisons',
        'comparison',
        ['PROGENY_BREED_WINNER', 'BEST_PROGENY_GROUP'],
        'Progeny group awards',
    ],
    [
        'couple final places',
        'placement',
        ['COUPLE_PLACE_1', 'COUPLE_PLACE_3'],
        'Group final placements',
    ],
    [
        'breeders final places',
        'placement',
        ['BREEDERS_PLACE_1', 'BREEDERS_PLACE_3'],
        'Group final placements',
    ],
    [
        'couple comparisons',
        'comparison',
        ['COUPLE_BREED_WINNER', 'BEST_COUPLE'],
        'Couple awards',
    ],
    [
        'breeders comparisons',
        'comparison',
        ['BREEDERS_BREED_WINNER', 'BEST_BREEDERS_GROUP'],
        'Breeders group awards',
    ],
    [
        'mixed group comparisons',
        'comparison',
        ['COUPLE_BREED_WINNER', 'BEST_BREEDERS_GROUP'],
        'Group awards',
    ],
    [
        'mixed career placements',
        'placement',
        ['PROGENY_PLACE_1', 'SHOW_PLACE_1'],
        'Class placements',
    ],
    [
        'mixed career comparisons',
        'comparison',
        ['PROGENY_BREED_WINNER', 'BOB'],
        'Best dog awards',
    ],
    [
        'historical placement without a code',
        'placement',
        [undefined],
        'Class placements',
    ],
] as const)(
    'uses an accurate section heading for %s',
    (_name, kind, codes, label) => {
        const groups = groupEventAwards(codes.map((code) => award(code, kind)));

        expect(groups[0].label).toBe(label);
        expect(groups[0].entries).toHaveLength(codes.length);
    },
);
