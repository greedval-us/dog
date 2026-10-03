<?php

namespace App\Modules\Pets\Enums;

enum PetHistoryUnit: string
{
    case Percent = 'percent';
    case Points = 'points';
    case Coins = 'coins';
    case Gems = 'gems';
}
