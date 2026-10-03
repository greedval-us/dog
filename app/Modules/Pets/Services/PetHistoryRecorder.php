<?php

namespace App\Modules\Pets\Services;

use App\Models\Pet;
use App\Models\PetHistoryEntry;
use App\Models\PetHistoryEvent;
use App\Modules\Pets\DTO\PetHistoryChange;
use Carbon\CarbonImmutable;

/**
 * @phpstan-import-type Change from PetHistoryChange
 * @phpstan-import-type Effect from \App\Modules\Pets\Calculators\PetStatusRules
 *
 * @phpstan-type Details array{stage?: 'started'|'completed', name?: array<string, string>|null, diseaseName?: array<string, string>|null, level?: int, experienceAwarded?: int, durationSeconds?: int, coins?: int, gems?: int, changes?: list<Change>, items?: list<array{category: string, name: array<string, string>|null, uses: int}>, confirmedAt?: string, statusRecovery?: array<string, int>, awardedEffects?: list<Effect>, automatic?: bool}
 */
final class PetHistoryRecorder
{
    /** @param Details $details */
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
