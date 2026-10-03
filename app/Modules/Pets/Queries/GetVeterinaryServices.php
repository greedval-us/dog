<?php

namespace App\Modules\Pets\Queries;

use App\Modules\Pets\DTO\VeterinaryServiceDefinition;
use App\Modules\Pets\Enums\VeterinaryService;

final class GetVeterinaryServices
{
    /** @return array<string, VeterinaryServiceDefinition> */
    public function handle(): array
    {
        $services = [];
        foreach (VeterinaryService::cases() as $service) {
            $services[$service->value] = $this->forService($service);
        }

        return $services;
    }

    public function forService(VeterinaryService $service): VeterinaryServiceDefinition
    {
        $price = config('veterinarian.prices.'.$service->value);

        return new VeterinaryServiceDefinition(
            service: $service,
            price: (int) $price,
            validPrice: is_int($price) && $price > 0,
            intervalDays: match ($service) {
                VeterinaryService::Treatment => 0,
                VeterinaryService::Checkup => (int) config('veterinarian.checkup_days'),
                VeterinaryService::Vaccination => (int) config('veterinarian.vaccination_days'),
            },
            healthRestorePercent: $service === VeterinaryService::Checkup ? (int) config('veterinarian.checkup_health_percent') : 0,
            healthRecoveryBonus: $service === VeterinaryService::Vaccination ? (int) config('veterinarian.vaccination_health_gain_percent') : 0,
        );
    }
}
