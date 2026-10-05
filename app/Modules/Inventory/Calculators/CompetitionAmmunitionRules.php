<?php

namespace App\Modules\Inventory\Calculators;

/** @phpstan-type Ammunition array{slot: 'body'|'line'|'handler'|'preparation', disciplines: list<string>, phase: 'preparation'|'performance', sizes: list<string>, modifiers: array{precision?:float|int, stamina?:float|int, pace?:float|int, focus?:float|int}, description: array{ru: string, en: string}} */
final class CompetitionAmmunitionRules
{
    public const DISCIPLINES = ['agility', 'nosework', 'canicross', 'conformation', 'progeny'];

    public const SLOTS = ['body', 'line', 'handler', 'preparation'];

    public const METRICS = ['precision', 'stamina', 'pace', 'focus'];

    /** @param array<string, mixed> $characteristics
     * @return Ammunition|null
     */
    public function metadata(array $characteristics): ?array
    {
        $data = $characteristics['competition'] ?? null;
        if (! is_array($data) || ! in_array($data['slot'] ?? null, self::SLOTS, true)
            || ! in_array($data['phase'] ?? null, ['preparation', 'performance'], true)
            || ! $this->validList($data['disciplines'] ?? null, self::DISCIPLINES)
            || ! $this->validList($data['sizes'] ?? null, ['small', 'medium', 'large'])
            || ! is_array($data['modifiers'] ?? null) || $data['modifiers'] === []
            || ! is_array($data['description'] ?? null)
            || ! is_string($data['description']['ru'] ?? null) || trim($data['description']['ru']) === ''
            || ! is_string($data['description']['en'] ?? null) || trim($data['description']['en']) === '') {
            return null;
        }

        $budget = 0.0;
        foreach ($data['modifiers'] as $metric => $modifier) {
            if (! in_array($metric, self::METRICS, true) || (! is_int($modifier) && ! is_float($modifier))
                || ! is_finite((float) $modifier) || $modifier < -0.08 || $modifier > 0.10) {
                return null;
            }
            $budget += abs($modifier);
        }
        if ($budget > 0.18 || ($data['slot'] === 'preparation' && $data['phase'] !== 'preparation')) {
            return null;
        }

        return [
            'slot' => $data['slot'], 'disciplines' => $data['disciplines'], 'phase' => $data['phase'],
            'sizes' => $data['sizes'], 'modifiers' => $data['modifiers'], 'description' => $data['description'],
        ];
    }

    /** @param Ammunition $metadata */
    public function supports(array $metadata, string $discipline, string $size, string $phase): bool
    {
        return $this->metadata(['competition' => $metadata]) !== null && $metadata['phase'] === $phase
            && in_array($discipline, $metadata['disciplines'], true) && in_array($size, $metadata['sizes'], true);
    }

    /** @param list<string> $allowed */
    private function validList(mixed $values, array $allowed): bool
    {
        if (! is_array($values) || ! array_is_list($values) || $values === [] || count($values) > count($allowed)) {
            return false;
        }
        foreach ($values as $value) {
            if (! is_string($value) || ! in_array($value, $allowed, true)) {
                return false;
            }
        }

        return count($values) === count(array_unique($values));
    }
}
