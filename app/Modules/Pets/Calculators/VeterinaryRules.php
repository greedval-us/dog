<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\DTO\VeterinaryServiceDefinition;
use App\Modules\Pets\Enums\VeterinaryService;
use App\Modules\Players\Enums\PlayerStatus;

final class VeterinaryRules
{
    public function petUnavailableReason(?PlayerStatus $status, bool $hasPet, bool $retired, bool $busy): ?string
    {
        return match (true) {
            $status !== PlayerStatus::Active => 'Your account is blocked.',
            ! $hasPet => 'Choose a dog to visit the veterinarian.',
            $retired => 'Retired dogs cannot visit the veterinarian.',
            $busy => 'Finish your dog’s current activity before visiting the veterinarian.',
            default => null,
        };
    }

    public function cooldownReason(VeterinaryService $service, ?int $availableAt, int $at): ?string
    {
        if ($service === VeterinaryService::Treatment || $availableAt === null || $availableAt <= $at) {
            return null;
        }

        return $service === VeterinaryService::Checkup
            ? 'Your dog has already had its weekly checkup.' : 'Your dog’s vaccination is still active.';
    }

    public function serviceUnavailableReason(VeterinaryServiceDefinition $definition, int $coins, ?int $availableAt, int $at): ?string
    {
        return $this->cooldownReason($definition->service, $availableAt, $at) ?? match (true) {
            ! $definition->validPrice => 'This veterinary service is unavailable.',
            $coins < $definition->price => 'You do not have enough coins for this veterinary service.',
            default => null,
        };
    }

    public function checkupHealth(float $health, float $maximum, int $restorePercent): float
    {
        $gain = $maximum * max(0, min(100, $restorePercent)) / 100;

        return round(min($maximum, $health + $gain), 4);
    }
}
