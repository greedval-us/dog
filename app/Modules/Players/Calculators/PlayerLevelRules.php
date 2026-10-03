<?php

namespace App\Modules\Players\Calculators;

use Brick\Math\BigInteger;
use InvalidArgumentException;

final class PlayerLevelRules
{
    /** @return array{level: int, levelExperience: string, requiredExperience: string, remainingExperience: string, percent: float, nextLevel: int} */
    public static function progress(string $totalExperience): array
    {
        if (preg_match('/\A(?:0|[1-9][0-9]*)\z/', $totalExperience) !== 1) {
            throw new InvalidArgumentException('Experience must be a non-negative integer.');
        }

        $total = BigInteger::of($totalExperience);
        $level = strlen($total->quotient(100)->plus(1)->toBase(2));
        $required = BigInteger::of(100)->multipliedBy(BigInteger::of(2)->power($level - 1));
        $current = $total->minus($required->minus(100));

        return [
            'level' => $level,
            'levelExperience' => (string) $current,
            'requiredExperience' => (string) $required,
            'remainingExperience' => (string) $required->minus($current),
            'percent' => $current->multipliedBy(10000)->quotient($required)->toInt() / 100.0,
            'nextLevel' => $level + 1,
        ];
    }
}
