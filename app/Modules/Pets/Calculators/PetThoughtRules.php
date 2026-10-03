<?php

namespace App\Modules\Pets\Calculators;

final class PetThoughtRules
{
    /**
     * @param  array<array-key, mixed>  $conditions  Untrusted conditions from the editable database catalogue.
     * @param  array<string, int|float|string|bool>  $values
     */
    public function matches(array $conditions, array $values): bool
    {
        foreach ($conditions as $condition) {
            if (! is_array($condition) || ! is_string($condition['field'] ?? null)
                || ! is_string($condition['operator'] ?? null) || ! isset($condition['value'])
                || ! is_scalar($condition['value'])) {
                return false;
            }
            $field = $condition['field'];
            if (! array_key_exists($field, $values)) {
                return false;
            }
            $actual = $values[$field];
            $expected = $condition['value'];
            if (is_float($actual) || is_int($actual)) {
                if (! is_numeric($expected)) {
                    return false;
                }
                $expected = (float) $expected;
                $actual = (float) $actual;
            } elseif (is_bool($actual)) {
                $expected = filter_var($expected, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($expected === null) {
                    return false;
                }
            }
            $matches = match ($condition['operator']) {
                'lt' => is_numeric($actual) && $actual < $expected,
                'lte' => is_numeric($actual) && $actual <= $expected,
                'gt' => is_numeric($actual) && $actual > $expected,
                'gte' => is_numeric($actual) && $actual >= $expected,
                'eq' => $actual === $expected,
                'neq' => $actual !== $expected,
                default => false,
            };
            if (! $matches) {
                return false;
            }
        }

        return true;
    }
}
