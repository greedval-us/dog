import type { EventDiscipline, GameEventEntry } from '@/types/game-event';

export function entryWasWithdrawn(entry?: GameEventEntry | null): boolean {
    return entry?.status === 'cancelled' || entry?.status === 'withdrawn';
}

export function isExhibition(discipline: EventDiscipline): boolean {
    return discipline === 'conformation' || discipline === 'progeny';
}

export function groupEventEntries(entries: GameEventEntry[]) {
    const divisions = new Map<string, GameEventEntry[]>();
    for (const entry of entries) {
        const group = divisions.get(entry.division);
        if (group) group.push(entry);
        else divisions.set(entry.division, [entry]);
    }

    return [...divisions.entries()].map(
        ([division, participants]) =>
            [
                division,
                participants.sort(
                    (first, second) =>
                        (first.rank ?? Infinity) - (second.rank ?? Infinity),
                ),
            ] as const,
    );
}
