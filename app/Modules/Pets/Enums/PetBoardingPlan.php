<?php

namespace App\Modules\Pets\Enums;

enum PetBoardingPlan: string
{
    case Off = 'off';
    case Cheap = 'cheap';
    case Premium = 'premium';
}
