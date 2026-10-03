<?php

namespace App\Modules\Pets\Queries;

use App\Models\PetHistoryEntry;
use App\Models\PetHistoryEvent;
use App\Models\User;
use Illuminate\Pagination\Cursor;

final class GetPetHistory
{
    /** @return array<string, mixed> */
    public function handle(User $user, int $petId, string $locale, string $kind = 'action', int $period = 7, ?string $event = null, ?Cursor $cursor = null): array
    {
        $user->pets()->findOrFail($petId, ['id']);
        $period = $period === 30 ? 30 : 7;
        $at = now();
        $query = PetHistoryEntry::query()->where('pet_id', $petId)->where('kind', $kind)
            ->whereBetween('occurred_at', [$at->subDays($period), $at]);
        if ($event !== null) {
            $query->where('event_code', $event);
        }
        $page = $query->orderByDesc('occurred_at')->orderByDesc('id')->cursorPaginate(20, ['*'], 'cursor', $cursor);
        $entries = array_map(function (PetHistoryEntry $entry) use ($locale): array {
            $details = $entry->details ?? [];
            foreach (['name', 'diseaseName'] as $field) {
                if (is_array($details[$field] ?? null)) {
                    $details[$field] = $this->label($details[$field], $locale);
                }
            }

            return [
                'id' => $entry->id, 'kind' => $entry->kind, 'eventCode' => $entry->event_code,
                'title' => $this->label($entry->title, $locale), 'message' => $entry->message === null ? null : $this->label($entry->message, $locale),
                'details' => $details, 'occurredAt' => $entry->occurred_at->toIso8601String(),
            ];
        }, $page->items());

        return [
            'data' => $entries, 'kind' => $kind, 'period' => $period,
            'nextCursor' => $page->nextCursor()?->encode(), 'previousCursor' => $page->previousCursor()?->encode(),
            'events' => PetHistoryEvent::query()->where('kind', $kind)->orderBy('id')->get(['code', 'name'])
                ->map(fn (PetHistoryEvent $event): array => ['code' => $event->code, 'name' => $this->label($event->name, $locale)])->all(),
        ];
    }

    /** @param array<string, mixed> $translations */
    private function label(array $translations, string $locale): string
    {
        return (string) ($translations[$locale] ?? $translations['ru'] ?? $translations['en'] ?? '');
    }
}
