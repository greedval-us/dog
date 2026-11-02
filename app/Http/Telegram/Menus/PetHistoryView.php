<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/** @phpstan-import-type HistoryEntryView from \App\Modules\Pets\Queries\GetPetHistory */
final class PetHistoryView
{
    public function __construct(private MenuView $view) {}

    /** @param HistoryEntryView $entry */
    public function text(array $entry, User $user, string $locale): string
    {
        $lines = [Str::limit($entry['title'], 120), $this->view->dateTime($entry['occurredAt'], $user)];
        if ($entry['message'] !== null) {
            $lines[] = Str::limit($entry['message'], 900);
        }
        $details = $entry['details'];
        if (in_array($details['stage'] ?? null, ['started', 'completed'], true)) {
            $lines[] = $this->view->translate('history.'.$details['stage'], $locale);
        }
        foreach (['name', 'diseaseName'] as $key) {
            if (is_string($details[$key] ?? null) && $details[$key] !== '') {
                $lines[] = Str::limit($details[$key], 120);
            }
        }
        foreach (['coins', 'gems', 'experienceAwarded'] as $key) {
            $value = $details[$key] ?? null;
            if ((is_int($value) || is_float($value)) && is_finite((float) $value) && $value !== 0) {
                $lines[] = $this->view->translate($key === 'experienceAwarded' ? 'experience' : $key, $locale).': '.($value > 0 ? '+' : '').round($value, 2);
            }
        }
        if (is_int($details['durationSeconds'] ?? null)) {
            $lines[] = $this->view->translate('history.duration', $locale, ['seconds' => $details['durationSeconds']]);
        }
        foreach (array_slice(is_array($details['changes'] ?? null) ? $details['changes'] : [], 0, 10) as $change) {
            if (is_array($change) && is_string($change['metric'] ?? null) && $this->numericChange($change)) {
                $metric = match ($change['metric']) {
                    'level', 'skill_level' => 'history.skill_level',
                    'coins', 'gems' => $change['metric'],
                    default => 'stats.'.$change['metric'],
                };
                $label = Lang::has('telegram.'.$metric, $locale) ? $this->view->translate($metric, $locale) : $this->view->translate('history.change', $locale);
                $suffix = match ($change['unit'] ?? null) {
                    'percent' => '%',
                    'coins', 'gems' => ' '.$this->view->translate($change['unit'], $locale),
                    default => '',
                };
                $lines[] = $label.': '.round($change['before'], 2).$suffix.' → '.round($change['after'], 2).$suffix.' ('.($change['delta'] > 0 ? '+' : '').round($change['delta'], 2).$suffix.')';
            }
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $change */
    private function numericChange(array $change): bool
    {
        foreach (['before', 'after', 'delta'] as $key) {
            if ((! is_int($change[$key] ?? null) && ! is_float($change[$key] ?? null)) || ! is_finite((float) $change[$key])) {
                return false;
            }
        }

        return true;
    }
}
