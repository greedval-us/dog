<?php

namespace App\Modules\Pets\Enums;

enum VeterinaryService: string
{
    case Treatment = 'treatment';
    case Checkup = 'checkup';
    case Vaccination = 'vaccination';
}
