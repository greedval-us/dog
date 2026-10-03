<?php

namespace App\Modules\Pets\Services;

use App\Models\Pet;
use App\Models\PetHistoryEntry;
use App\Models\PetHistoryEvent;
use Carbon\CarbonImmutable;

final class PetHistoryRecorder
{
    /** @param array<string, mixed> $details */
    public function record(Pet $pet, string $code, string $sourceKey, CarbonImmutable $occurredAt, array $details = []): ?PetHistoryEntry
    {
        if ($occurredAt->lessThan(now()->subDays(30))) {
            return null;
        }
        $event = PetHistoryEvent::query()->where('code', $code)->where('kind', 'action')->where('is_active', true)->first();
        if ($event === null) {
            return null;
        }

        return PetHistoryEntry::query()->firstOrCreate(['pet_id' => $pet->id, 'source_key' => $sourceKey], [
            'pet_history_event_id' => $event->id, 'kind' => 'action', 'event_code' => $event->code,
            'title' => $event->name, 'details' => $details, 'occurred_at' => $occurredAt,
        ]);
    }
}
