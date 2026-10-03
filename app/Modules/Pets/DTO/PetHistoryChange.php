<?php

namespace App\Modules\Pets\DTO;

use App\Modules\Pets\Enums\PetHistoryUnit;
use InvalidArgumentException;

/** @phpstan-type Change array{metric: string, before: float, after: float, delta: float, unit: 'percent'|'points'|'coins'|'gems'} */
final readonly class PetHistoryChange
{
    private function __construct(
        public string $metric,
        public float $before,
        public float $after,
        public float $delta,
        public PetHistoryUnit $unit,
    ) {}

    public static function percent(string $metric, float $before, float $after): self
    {
        return self::between($metric, $before, $after, PetHistoryUnit::Percent);
    }

    public static function points(string $metric, float $before, float $after): self
    {
        return self::between($metric, $before, $after, PetHistoryUnit::Points);
    }

    private static function between(string $metric, float $before, float $after, PetHistoryUnit $unit): self
    {
        if (trim($metric) === '' || ! is_finite($before) || ! is_finite($after) || ! is_finite($after - $before)) {
            throw new InvalidArgumentException('History changes require a metric and finite values.');
        }

        return new self($metric, round($before, 4), round($after, 4), round($after - $before, 4), $unit);
    }

    /** @return Change */
    public function toArray(): array
    {
        return [
            'metric' => $this->metric, 'before' => $this->before, 'after' => $this->after,
            'delta' => $this->delta, 'unit' => $this->unit->value,
        ];
    }
}
