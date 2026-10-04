<?php

namespace App\Modules\Pets\Services;

use App\Models\GameEvent;
use Carbon\CarbonImmutable;

final class GameEventSchedule
{
    public function ensureUpcoming(?CarbonImmutable $at = null): void
    {
        $at = ($at ?? CarbonImmutable::now())->setTimezone(config('game-events.timezone'));
        foreach (config('game-events.disciplines') as $discipline => $settings) {
            foreach (range(0, 7) as $offset) {
                $this->create($discipline, 'daily', $at->startOfDay()->addDays($offset)->setTimeFromTimeString($settings['daily_time']), $at, $settings);
            }
            $sunday = $at->startOfWeek()->addDays(6)->setTime(20, 0);
            foreach ([$sunday, $sunday->addWeek()] as $date) {
                $this->create($discipline, 'weekly', $date, $at, $settings);
            }
            $monthEnd = $at->endOfMonth()->setTime(20, 30);
            foreach ([$monthEnd, $at->startOfMonth()->addMonth()->endOfMonth()->setTime(20, 30)] as $date) {
                $this->create($discipline, 'monthly', $date, $at, $settings);
            }
        }
    }

    /** @param array<string, mixed> $settings */
    private function create(string $discipline, string $frequency, CarbonImmutable $startsAt, CarbonImmutable $at, array $settings): void
    {
        $closesAt = $startsAt->subMinutes(config('game-events.closing_minutes', 15));
        if ($closesAt->lessThanOrEqualTo($at)) {
            return;
        }
        $opensAt = match ($frequency) {
            'weekly' => $startsAt->subWeek(),
            'monthly' => $startsAt->subDays(30),
            default => $startsAt->subDay(),
        };
        $fees = config('game-events.frequencies.'.$frequency);
        GameEvent::query()->firstOrCreate(['discipline' => $discipline, 'frequency' => $frequency, 'starts_at' => $startsAt->utc()], [
            'status' => 'registration', 'registration_opens_at' => $opensAt->utc(), 'closes_at' => $closesAt->utc(),
            'ends_at' => $startsAt->addMinutes(config('game-events.duration_minutes', 10))->utc(),
            'seed' => bin2hex(random_bytes(16)),
            'rules' => ['version' => 1, 'fee' => $fees['fee'], 'prizes' => $fees['prizes'], 'stages' => $settings['stages'], 'energy_cost' => $settings['energy_cost'], 'field_size' => config('game-events.field_size', 8), 'time_limit' => 360],
        ]);
    }
}
