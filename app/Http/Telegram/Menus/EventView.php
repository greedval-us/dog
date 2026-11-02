<?php

namespace App\Http\Telegram\Menus;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class EventView
{
    public function __construct(private MenuView $view) {}

    /** @param array<string,mixed> $event */
    public function eventName(array $event, string $locale): string
    {
        return (string) trans('events.disciplines.'.$event['discipline'], [], $locale).' · '.$this->view->translate('event.frequency.'.$event['frequency'], $locale);
    }

    /** @param array<string,mixed> $event */
    public function eventText(array $event, User $user, string $locale): string
    {
        $lines = [$this->eventName($event, $locale), $this->view->translate('event.status.'.$event['status'], $locale),
            $this->view->translate('event.opens', $locale, ['time' => $this->view->dateTime($event['opensAt'], $user)]),
            $this->view->translate('event.starts', $locale, ['time' => $this->view->dateTime($event['startsAt'], $user)]),
            $this->view->translate('event.deadline', $locale, ['time' => $this->view->dateTime($event['closesAt'], $user)]),
            $this->view->translate('event.entry_fee', $locale, ['fee' => $event['fee']]),
            $this->view->translate('event.participants', $locale, ['count' => $event['entryCount']]),
        ];
        if ($event['prizes'] !== []) {
            $lines[] = $this->view->translate('event.prizes', $locale).': '.implode(' / ', $event['prizes']).' '.$this->view->translate('coins', $locale);
        }
        foreach (['rankLabel', 'formatLabel', 'specialShowLabel', 'showLegLabel', 'regionLabel', 'policyNote'] as $key) {
            if (is_string($event['regulation'][$key] ?? null) && $event['regulation'][$key] !== '') {
                $lines[] = Str::limit($event['regulation'][$key], 350);
            }
        }
        $rules = $event['participationRules'];
        $lines[] = $this->view->translate('event.limits', $locale, ['player' => $rules['playerDailyLimit'], 'pet' => $rules['petDailyLimit'], 'hours' => $rules['petRestHours']]);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string,mixed>  $event
     * @param  array<string,mixed>  $entry
     */
    public function editable(array $event, array $entry): bool
    {
        return $event['status'] === 'scheduled' && $entry['status'] === 'registered' && CarbonImmutable::parse($event['closesAt'])->isFuture();
    }

    /** @param array<string,mixed> $entry */
    public function entryText(array $entry, User $user, string $locale): string
    {
        $lines = [Str::limit($entry['name'], 120), $this->view->translate('event.entry_status.'.$entry['status'], $locale), Str::limit($entry['divisionLabel'], 160)];
        if ($entry['rank'] !== null) {
            $lines[] = $this->view->translate('event.rank', $locale, ['rank' => $entry['rank']]);
        } elseif ($entry['status'] === 'completed') {
            $lines[] = $this->view->translate('event.unranked', $locale);
        }
        if ($entry['prize'] > 0) {
            $lines[] = $this->view->translate('event.prize', $locale, ['coins' => $entry['prize']]);
        }
        $result = $entry['result'];
        if ($result === null && in_array($entry['status'], ['registered', 'frozen'], true)) {
            $lines[] = $this->view->translate('event.awaiting_result', $locale);
        } elseif ($result !== null) {
            if (is_string($result['assessment']['label'] ?? null)) {
                $lines[] = $result['assessment']['label'];
            }
            if (is_string($result['reason'] ?? null)) {
                $lines[] = Str::limit($result['reason'], 350);
            }
            foreach (['score', 'time', 'penalties'] as $key) {
                if (is_int($result[$key] ?? null) || is_float($result[$key] ?? null)) {
                    $lines[] = $this->view->translate('event.'.$key, $locale).': '.round($result[$key], 2);
                }
            }
            foreach (array_slice($result['standings'] ?? [], 0, 6) as $standing) {
                $line = Str::limit((string) ($standing['label'] ?? ''), 100).(($standing['rank'] ?? null) === null ? '' : ' · '.$this->view->translate('event.rank', $locale, ['rank' => $standing['rank']]));
                foreach (['score', 'time', 'penalties'] as $key) {
                    if (is_int($standing[$key] ?? null) || is_float($standing[$key] ?? null)) {
                        $line .= ' · '.$this->view->translate('event.'.$key, $locale).': '.round($standing[$key], 2);
                    }
                }
                if (is_bool($standing['qualified'] ?? null)) {
                    $line .= ' · '.trans('events.grades.'.($standing['qualified'] ? 'qualified' : 'not_qualified'), [], $locale);
                }
                $lines[] = $line;
            }
            foreach (array_slice($result['stages'] ?? [], 0, 6) as $index => $stage) {
                $label = (string) trans('events.stages.'.$stage['key'], [], $locale);
                if (str_starts_with($label, 'events.')) {
                    $label = $this->view->translate('event.stage_number', $locale, ['number' => $index + 1]);
                }
                $lines[] = $label.' · '.$this->view->translate('event.decisions.'.($stage['decision'] ?? 'balanced'), $locale)
                    .' · '.$this->view->translate('event.score', $locale).': '.round($stage['score'] ?? 0, 2)
                    .' · '.$this->view->translate('event.penalties', $locale).': '.($stage['penalties'] ?? 0);
                if (is_string($stage['reason'] ?? null)) {
                    $lines[] = Str::limit($stage['reason'], 180);
                }
            }
        }
        foreach (array_slice($entry['awards'], 0, 10) as $award) {
            $lines[] = $this->view->translate('event.award', $locale).': '.Str::limit($award['name'], 150);
        }
        if (($entry['plan']['stages'] ?? []) !== []) {
            $lines[] = $this->view->translate('event.plan', $locale).': '.implode(' / ', array_map(fn (string $decision): string => $this->view->translate('event.decisions.'.$decision, $locale), $entry['plan']['stages']));
        }

        return implode("\n", $lines);
    }
}
