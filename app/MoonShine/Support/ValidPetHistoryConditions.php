<?php

namespace App\MoonShine\Support;

use App\Modules\Pets\Enums\PetActivity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidPetHistoryConditions implements ValidationRule
{
    public const STATE_FIELDS = ['health', 'energy', 'satiety', 'hydration', 'mood', 'cleanliness', 'bond'];

    public const OPERATORS = ['lt', 'lte', 'gt', 'gte', 'eq', 'neq'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $condition) {
            if (! is_array($condition) || ! isset($condition['field'], $condition['operator']) || ! array_key_exists('value', $condition)) {
                $fail(__('admin.history_invalid_condition'));

                return;
            }

            $field = $condition['field'];
            $operator = $condition['operator'];
            $threshold = $condition['value'];

            if (! in_array($operator, self::OPERATORS, true)) {
                $fail(__('admin.history_invalid_condition'));

                return;
            }

            $valid = match (true) {
                in_array($field, self::STATE_FIELDS, true) => is_numeric($threshold)
                    && is_finite((float) $threshold) && (float) $threshold >= 0 && (float) $threshold <= 100,
                $field === 'activity' => in_array($operator, ['eq', 'neq'], true)
                    && in_array($threshold, ['idle', ...array_column(PetActivity::cases(), 'value')], true),
                $field === 'has_disease' => in_array($operator, ['eq', 'neq'], true)
                    && in_array($threshold, [true, false, 0, 1, '0', '1'], true),
                default => false,
            };

            if (! $valid) {
                $fail(__('admin.history_invalid_condition'));

                return;
            }
        }
    }
}
