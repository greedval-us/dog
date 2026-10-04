<?php

namespace App\Modules\Pets\Calculators;

final class GameEventRandomness
{
    /** @return list<float> */
    public function draws(string $key, int $count): array
    {
        $draws = [];
        for ($index = 0; $index < $count; $index++) {
            $draws[] = hexdec(substr(hash('sha256', $key.':'.$index), 0, 8)) / 4294967295;
        }

        return $draws;
    }

    public function token(string $key): string
    {
        $hash = hash('sha256', $key);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-8'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }
}
