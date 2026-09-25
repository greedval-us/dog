<?php

namespace App\Modules\Players\Enums;

enum PlayerStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
}
