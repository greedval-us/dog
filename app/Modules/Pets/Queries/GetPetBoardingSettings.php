<?php

namespace App\Modules\Pets\Queries;

use App\Models\User;
use App\Modules\Pets\Calculators\PetBoardingRules;
use App\Modules\Pets\Enums\PetBoardingPlan;

final class GetPetBoardingSettings
{
    public function __construct(private PetBoardingRules $rules) {}

    /** @return array{plan: string, inactivitySeconds: int, hourlyPrices: array{cheap: int, premium: int}} */
    public function handle(User $user): array
    {
        return [
            'plan' => $user->boarding_plan->value,
            'inactivitySeconds' => $this->rules->inactivitySeconds(),
            'hourlyPrices' => [
                'cheap' => $this->rules->hourlyPrice(PetBoardingPlan::Cheap),
                'premium' => $this->rules->hourlyPrice(PetBoardingPlan::Premium),
            ],
        ];
    }
}
