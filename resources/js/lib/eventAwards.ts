import type { EventAwardKind, EventTitle } from '@/types/game-event';

export const eventAwardLabels: Record<EventAwardKind, string> = {
    championship: 'Championship titles',
    certificate: 'Certificates',
    comparison: 'Best dog awards',
    qualification: 'Qualifications',
    placement: 'Class placements',
    legacy: 'Historical game awards',
};

export function groupEventAwards<T extends EventTitle>(awards: T[]) {
    return (Object.keys(eventAwardLabels) as EventAwardKind[]).flatMap(
        (kind) => {
            const entries = awards.filter(
                (award) => (award.kind ?? 'legacy') === kind,
            );
            return entries.length
                ? [{ kind, label: eventAwardLabels[kind], entries }]
                : [];
        },
    );
}
