<?php

namespace App\Modules\Pets\Services;

use App\Models\Dog;
use App\Models\GameEvent;
use App\Modules\Pets\Calculators\GameEventCalendar;
use App\Modules\Pets\Calculators\RegulatedEventRules;
use App\Modules\Pets\Calculators\RegulatedEventSimulator;
use App\Modules\Pets\Enums\GameEventDiscipline;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GameEventSchedule
{
    public function __construct(private RegulatedEventRules $rules, private GameEventCalendar $calendar) {}

    public function ensureUpcoming(?CarbonImmutable $at = null): void
    {
        $at = ($at ?? CarbonImmutable::now())->setTimezone(config('game-events.timezone'));
        foreach (config('game-events.disciplines') as $discipline => $settings) {
            $discipline = GameEventDiscipline::from($discipline)->value;
            foreach (range(0, 7) as $offset) {
                $this->create($discipline, 'daily', $at->startOfDay()->addDays($offset)->setTimeFromTimeString($settings['daily_time']), $at, $settings);
                if ($discipline === 'nosework') {
                    $this->create($discipline, 'daily', $at->startOfDay()->addDays($offset)->setTimeFromTimeString($settings['daily_time'])
                        ->subHours(config('game-events.regulation_calendar.odor_test_lead_hours')), $at, $settings, odorTest: true);
                }
                if ($discipline === 'conformation') {
                    $this->create($discipline, 'daily', $at->startOfDay()->addDays($offset)->setTimeFromTimeString($settings['daily_time'])
                        ->addHours(config('game-events.regulation_calendar.specialty_delay_hours')), $at, $settings, specialty: true);
                }
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
        $this->specialExhibitions($at);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $overrides
     */
    private function create(string $discipline, string $frequency, CarbonImmutable $startsAt, CarbonImmutable $at, array $settings, bool $odorTest = false, bool $specialty = false, array $overrides = []): void
    {
        $closesAt = $startsAt->subMinutes(config('game-events.closing_minutes', 15));
        if ($closesAt->lessThanOrEqualTo($at)) {
            return;
        }
        $opensAt = isset($overrides['specialShow']) ? $startsAt->subDay() : match ($frequency) {
            'weekly' => $startsAt->subWeek(),
            'monthly' => $startsAt->subDays(30),
            default => $startsAt->subDay(),
        };
        $fees = config('game-events.frequencies.'.$frequency);
        $regulation = [...$this->regulation($discipline, $frequency, $startsAt, $odorTest, $specialty), ...$overrides];
        $sportScope = $regulation['sportScope'] ?? 'general';
        $minimum = config('sport_titles.minimum_participants.'.$sportScope.'.'.$discipline.'.'.($regulation['system'] === 'fci' ? 'international' : $regulation['rank']), 0);
        $rules = [
            'version' => RegulatedEventSimulator::VERSION, 'fee' => $fees['fee'], 'prizes' => $fees['prizes'],
            'stages' => $this->rules->stages($discipline, $regulation, []), 'energy_cost' => $settings['energy_cost'],
            'field_size' => max(config('game-events.field_size', 8), $minimum), 'time_limit' => config('game-events.regulation_calendar.maximum_course_seconds'),
            'regulation' => $regulation,
        ];
        $identity = ['discipline' => $discipline, 'frequency' => $frequency, 'starts_at' => $startsAt->utc()];
        $event = GameEvent::query()->firstOrCreate($identity, [
            'status' => 'registration', 'registration_opens_at' => $opensAt->utc(), 'closes_at' => $closesAt->utc(),
            'ends_at' => $startsAt->addMinutes(config('game-events.duration_minutes', 10))->utc(),
            'seed' => bin2hex(random_bytes(16)),
            'rules' => $rules,
        ]);
        if (! $event->wasRecentlyCreated && (($event->rules['version'] ?? 0) !== RegulatedEventSimulator::VERSION
            || ($event->rules['regulation']['version'] ?? 0) !== ($regulation['version'] ?? 0))) {
            DB::transaction(function () use ($event, $rules, $at): void {
                $locked = GameEvent::query()->lockForUpdate()->findOrFail($event->id);
                if ($locked->status === 'registration' && $locked->closes_at->isAfter($at) && ! $locked->entries()->exists()) {
                    $locked->update(['rules' => $rules]);
                }
            });
        }
    }

    /** @return array<string,mixed> */
    private function regulation(string $discipline, string $frequency, CarbonImmutable $at, bool $odorTest, bool $specialty): array
    {
        $calendar = config('game-events.regulation_calendar');
        $day = (int) $at->utc()->startOfDay()->diffInDays(CarbonImmutable::create(2026, 1, 1, 0, 0, 0, 'UTC'), absolute: true);
        $choices = $calendar['disciplines'][$discipline][$frequency];
        $choice = $specialty ? ['rank' => 'specialty'] : $choices[$day % count($choices)];
        $international = ($choice['system'] ?? 'rkf') === 'fci';
        $country = $international ? ($choice['country'] ?? $calendar['international_countries'][intdiv($day, count($choices)) % count($calendar['international_countries'])]) : 'RU';
        $judge = $calendar['judges'][($day + intdiv($day, 7)) % count($calendar['judges'])];
        $regulation = [
            'version' => config('event_regulations.version'), 'system' => $international ? 'fci' : 'rkf',
            'rank' => $choice['rank'], 'format' => $odorTest ? 'odor_test' : ($choice['format'] ?? $discipline),
            'country' => $country, 'region' => $international ? strtolower($country) : $calendar['regions'][$day % count($calendar['regions'])],
            'judgeId' => $judge['id'], 'judgeName' => $judge['name'],
            'sourceUrl' => config('event_regulations.sources.'.match ($discipline) {
                'conformation', 'progeny' => $international ? 'fci_shows_2026' : 'rkf_shows_2026',
                'agility' => $international ? 'fci_agility' : 'rkf_agility_2026',
                'nosework' => 'rkf_nosework_2026_09', 'canicross' => 'rkf_racing_2026_06',
                default => throw new \InvalidArgumentException('Unknown scheduled event discipline.'),
            }),
            'sportScope' => $choice['sportScope'] ?? 'general',
        ];
        if ($odorTest) {
            $regulation['rank'] = 'qualifying';
        }
        if ($choice['rank'] === 'specialty') {
            $breeds = Dog::query()->where('event_profile->recognized_rkf', true)->orderBy('id')->pluck('id');
            if ($breeds->isNotEmpty()) {
                $regulation['breedId'] = $breeds[$day % $breeds->count()];
            } else {
                $regulation['rank'] = 'cac_cf';
            }
        }
        if ($regulation['sportScope'] === 'breed') {
            $breeds = Dog::query()->where('event_profile->recognized_rkf', true)->orderBy('id')->pluck('id');
            if ($breeds->isNotEmpty()) {
                $regulation['breedId'] = $breeds[$day % $breeds->count()];
            } else {
                $regulation['sportScope'] = 'general';
            }
        }
        if ($discipline === 'agility' && ! $international && in_array($regulation['rank'], ['certificate', 'championship', 'cup', 'cac_rkf', 'cac_rkf_special'], true)) {
            $regulation['format'] = 'agility_combined';
        }
        if ($discipline === 'nosework' && in_array($regulation['rank'], ['certificate', 'championship', 'cup', 'cac_rkf', 'cac_rkf_special'], true)) {
            $regulation['certificatePolicy'] = 'nw_first_place';
        }
        $regulation['sources'] = [
            ['key' => 'discipline', 'url' => $regulation['sourceUrl']],
            ['key' => GameEventDiscipline::from($discipline)->isExhibition() ? 'rkf_championships' : 'rkf_sport_titles', 'url' => config('event_regulations.sources.'.(GameEventDiscipline::from($discipline)->isExhibition() ? 'rkf_championships' : 'rkf_sport_titles'))],
        ];

        return $regulation;
    }

    private function specialExhibitions(CarbonImmutable $at): void
    {
        $calendar = config('game-events.special_calendar');
        $settings = config('game-events.disciplines.conformation');
        $breeds = Dog::query()->where('event_profile->recognized_rkf', true)->orderBy('id')->get();
        foreach (range(0, $calendar['days_ahead']) as $offset) {
            $date = $at->startOfDay()->addDays($offset);
            $edition = $this->calendar->editionAt($date);
            foreach ($calendar['events'] as $showIndex => $show) {
                $startsAt = $date->setTimeFromTimeString($show['time']);
                $judges = config('game-events.regulation_calendar.judges');
                $judge = $judges[($edition['monthIndex'] + $showIndex) % count($judges)];
                $source = config('special_exhibitions.sources.'.(in_array($show['specialShow'], ['eurasia', 'russia'], true) ? $show['specialShow'] : 'titles'));
                $this->create('conformation', 'monthly', $startsAt, $at, $settings, overrides: [
                    'system' => 'rkf', 'country' => 'RU', 'region' => 'moscow', 'rank' => 'cac_rkf_special', 'format' => 'conformation',
                    'specialShow' => $show['specialShow'], 'showLeg' => $show['showLeg'],
                    'judgeId' => $judge['id'], 'judgeName' => $judge['name'],
                    'showYear' => $edition['showYear'], 'showEdition' => $edition['showEdition'],
                    'sourceUrl' => $source,
                    'sources' => [['key' => $show['specialShow'], 'url' => $source], ['key' => 'rkf_championships', 'url' => config('special_exhibitions.sources.titles')]],
                ]);
            }
            $reservedTimes = array_fill_keys([...array_column($calendar['events'], 'time'), ...$calendar['reserved_times']], true);
            $breedStartsAt = $date->setTimeFromTimeString($calendar['breed_time']);
            foreach ($breeds as $breed) {
                $profile = $breed->eventProfileDefaults();
                $series = $profile['club_system'] === 'nkp' ? 'national_club' : 'national_breed';
                while (isset($reservedTimes[$breedStartsAt->format('H:i')])) {
                    $breedStartsAt = $breedStartsAt->addMinutes($calendar['breed_interval_minutes']);
                }
                $startsAt = $breedStartsAt;
                $breedStartsAt = $breedStartsAt->addMinutes($calendar['breed_interval_minutes']);
                $this->create('conformation', 'monthly', $startsAt, $at, $settings, overrides: [
                    'system' => 'rkf', 'country' => 'RU', 'rank' => 'specialty', 'format' => 'conformation', 'breedId' => $breed->id,
                    'specialShow' => $series, 'showLeg' => '', 'showYear' => $edition['showYear'], 'showEdition' => $edition['showEdition'],
                    'sourceUrl' => config('special_exhibitions.sources.shows'),
                    'sources' => [['key' => 'rkf_shows_2026', 'url' => config('special_exhibitions.sources.shows')], ['key' => 'rkf_championships', 'url' => config('special_exhibitions.sources.titles')]],
                ]);
            }
        }
    }
}
