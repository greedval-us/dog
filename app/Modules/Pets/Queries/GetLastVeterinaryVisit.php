<?php

namespace App\Modules\Pets\Queries;

use App\Models\VeterinaryVisit;
use App\Modules\Pets\Enums\VeterinaryService;

final class GetLastVeterinaryVisit
{
    public function handle(int $petId, VeterinaryService $service): ?VeterinaryVisit
    {
        if ($service === VeterinaryService::Treatment) {
            return null;
        }

        return VeterinaryVisit::query()->where('pet_id', $petId)->where('service', $service)
            ->whereNotNull('available_at')->orderByDesc('available_at')->first();
    }
}
