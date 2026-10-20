<?php

namespace App\Modules\Kennel\Enums;

enum KennelPetAge: string
{
    case Puppy = '3';
    case Young = '18';

    public const int DEFAULT_MONTHS = 3;
}
