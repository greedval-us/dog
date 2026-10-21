import type { EventAwardKind, EventTitle } from '@/types/game-event';

export const eventAwardLabels: Record<EventAwardKind, string> = {
    championship: 'Championship titles',
    certificate: 'Certificates',
    comparison: 'Best dog awards',
    qualification: 'Qualifications',
    placement: 'Class placements',
    legacy: 'Historical game awards',
};

const groupComparisonAwards = [
    {
        codes: ['PROGENY_BREED_WINNER', 'BEST_PROGENY_GROUP'],
        label: 'Progeny group awards',
    },
    { codes: ['COUPLE_BREED_WINNER', 'BEST_COUPLE'], label: 'Couple awards' },
    {
        codes: ['BREEDERS_BREED_WINNER', 'BEST_BREEDERS_GROUP'],
        label: 'Breeders group awards',
    },
];

export function groupEventAwards<T extends EventTitle>(awards: T[]) {
    return (Object.keys(eventAwardLabels) as EventAwardKind[]).flatMap(
        (kind) => {
            const entries = awards.filter(
                (award) => (award.kind ?? 'legacy') === kind,
            );
            if (!entries.length) return [];
            let label = eventAwardLabels[kind];
            if (
                kind === 'placement' &&
                entries.every((award) =>
                    /^(PROGENY|COUPLE|BREEDERS)_PLACE_[1-3]$/.test(
                        award.code ?? '',
                    ),
                )
            ) {
                label = 'Group final placements';
            }
            if (
                kind === 'comparison' &&
                entries.every((award) =>
                    groupComparisonAwards.some((group) =>
                        group.codes.includes(award.code ?? ''),
                    ),
                )
            ) {
                label =
                    groupComparisonAwards.find((group) =>
                        entries.every((award) =>
                            group.codes.includes(award.code ?? ''),
                        ),
                    )?.label ?? 'Group awards';
            }
            return [{ kind, label, entries }];
        },
    );
}
