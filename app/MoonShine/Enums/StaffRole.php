<?php

namespace App\MoonShine\Enums;

enum StaffRole: string
{
    case Administrator = 'administrator';
    case Analyst = 'analyst';
    case Moderator = 'moderator';

    public function label(): string
    {
        return __('admin.roles.'.$this->value);
    }
}
