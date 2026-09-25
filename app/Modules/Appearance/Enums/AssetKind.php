<?php

namespace App\Modules\Appearance\Enums;

enum AssetKind: string
{
    case Portrait = 'portrait';
    case Background = 'background';

    public function petColumn(): string
    {
        return match ($this) {
            self::Portrait => 'portrait_asset_id',
            self::Background => 'background_asset_id',
        };
    }
}
