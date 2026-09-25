<?php

namespace App\Modules\Pets\Enums;

enum PetActivity: string
{
    case Training = 'training';
    case Walk = 'walk';
    case Competition = 'competition';
    case Exhibition = 'exhibition';
}
