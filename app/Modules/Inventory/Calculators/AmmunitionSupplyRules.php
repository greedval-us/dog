<?php

namespace App\Modules\Inventory\Calculators;

use InvalidArgumentException;

final class AmmunitionSupplyRules
{
    private const BASIC = ['intervalHours' => 6, 'stockTarget' => 18, 'purchaseLimit' => 2, 'usageLimit' => 40];

    private const SPECIALIZED = ['intervalHours' => 168, 'stockTarget' => 6, 'purchaseLimit' => 1, 'usageLimit' => 60];

    /** @return array{intervalHours: int, stockTarget: int, purchaseLimit: int, usageLimit: int} */
    public function supply(bool $specialized): array
    {
        return $specialized ? self::SPECIALIZED : self::BASIC;
    }

    public function validInterval(?int $hours): bool
    {
        return $hours === self::BASIC['intervalHours'] || $hours === self::SPECIALIZED['intervalHours'];
    }

    /** @return array{current: int, next: int} */
    public function schedule(int $at, int $anchor, int $intervalHours): array
    {
        if (! $this->validInterval($intervalHours) || $at < $anchor) {
            throw new InvalidArgumentException('Invalid ammunition delivery schedule.');
        }

        $seconds = $intervalHours * 3600;
        $current = $anchor + intdiv($at - $anchor, $seconds) * $seconds;

        return ['current' => $current, 'next' => $current + $seconds];
    }
}
