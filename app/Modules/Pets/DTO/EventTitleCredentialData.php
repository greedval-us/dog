<?php

namespace App\Modules\Pets\DTO;

use App\Models\PetTitle;

final readonly class EventTitleCredentialData
{
    /** @return array<string,mixed> */
    public static function fromTitle(PetTitle $title): array
    {
        return [
            'title_id' => $title->id, 'entry_id' => $title->game_event_entry_id,
            'code' => $title->code, 'kind' => $title->kind, 'scope_key' => $title->scope_key,
            'regulation_version' => $title->regulation_version, 'discipline' => $title->discipline,
            'awarded_at' => $title->awarded_at->getTimestamp(),
            'judgeId' => $title->details['judgeId'] ?? '', 'country' => $title->details['country'] ?? '',
            'region' => $title->details['region'] ?? '', 'rank' => $title->details['rank'] ?? '',
            'details' => [...$title->details, 'used_for' => $title->relationLoaded('consumptions') ? $title->consumptions->pluck('purpose')->all() : []],
        ];
    }
}
