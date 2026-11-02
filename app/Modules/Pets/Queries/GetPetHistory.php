<?php

namespace App\Modules\Pets\Queries;

use App\Models\PetHistoryEntry;
use App\Models\PetHistoryEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;

/**
 * @phpstan-type HistoryEntryView array{id: int, kind: string, eventCode: string, title: string, message: string|null, details: array<string, mixed>, occurredAt: string}
 * @phpstan-type HistoryPage array{data: array<int, HistoryEntryView>, kind: string, period: int, nextCursor: string|null, previousCursor: string|null, events: array<int, array{code: string, name: string}>}
 */
final class GetPetHistory
{
    public const PAGE_SIZE = 20;

    /** @return HistoryPage */
    public function handle(User $user, int $petId, string $locale, string $kind = 'action', int $period = 7, ?string $event = null, ?Cursor $cursor = null): array
    {
        $period = $period === 30 ? 30 : 7;
        $page = $this->entries($user, $petId, $kind, $period, $event)->orderByDesc('occurred_at')->orderByDesc('id')->cursorPaginate(self::PAGE_SIZE, ['*'], 'cursor', $cursor);
        $entries = array_map(fn (PetHistoryEntry $entry): array => $this->entryView($entry, $locale), $page->items());

        return [
            'data' => $entries, 'kind' => $kind, 'period' => $period,
            'nextCursor' => $page->nextCursor()?->encode(), 'previousCursor' => $page->previousCursor()?->encode(),
            'events' => PetHistoryEvent::query()->where('kind', $kind)->orderBy('id')->get(['code', 'name'])
                ->map(fn (PetHistoryEvent $event): array => ['code' => $event->code, 'name' => $this->label($event->name, $locale)])->all(),
        ];
    }

    /** @return HistoryEntryView */
    public function find(User $user, int $petId, int $entryId, string $locale, string $kind = 'action', int $period = 7, ?string $event = null): array
    {
        return $this->entryView($this->entries($user, $petId, $kind, $period, $event)->findOrFail($entryId), $locale);
    }

    /** @return Builder<PetHistoryEntry> */
    private function entries(User $user, int $petId, string $kind, int $period, ?string $event): Builder
    {
        $user->pets()->findOrFail($petId, ['id']);
        $at = now();
        $query = PetHistoryEntry::query()->where('pet_id', $petId)->where('kind', $kind)
            ->whereBetween('occurred_at', [$at->subDays($period === 30 ? 30 : 7), $at]);
        if ($event !== null) {
            $query->where('event_code', $event);
        }

        return $query;
    }

    /** @return HistoryEntryView */
    private function entryView(PetHistoryEntry $entry, string $locale): array
    {
        $details = $entry->details ?? [];
        foreach (['name', 'diseaseName'] as $field) {
            if (is_array($details[$field] ?? null)) {
                $details[$field] = $this->label($details[$field], $locale);
            }
        }

        return ['id' => $entry->id, 'kind' => $entry->kind, 'eventCode' => $entry->event_code,
            'title' => $this->label($entry->title, $locale), 'message' => $entry->message === null ? null : $this->label($entry->message, $locale),
            'details' => $details, 'occurredAt' => $entry->occurred_at->toIso8601String()];
    }

    /** @param array<string, mixed> $translations */
    private function label(array $translations, string $locale): string
    {
        return (string) ($translations[$locale] ?? $translations['ru'] ?? $translations['en'] ?? '');
    }
}
