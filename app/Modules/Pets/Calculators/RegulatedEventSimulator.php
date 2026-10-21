<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\DTO\GameEventProtocol;
use InvalidArgumentException;

/** @phpstan-import-type Gear from GameEventProtocol */
final class RegulatedEventSimulator
{
    public const VERSION = 3;

    public const SNAPSHOT_VERSION = 1;

    /** @param array<string,mixed> $balance */
    public function __construct(private array $balance, private GameEventPerformance $performance = new GameEventPerformance) {}

    /**
     * @param  array<string,mixed>  $first
     * @param  array<string,mixed>  $second
     */
    public function compareResults(string $discipline, array $first, array $second, int $version = self::VERSION): int
    {
        if ($version !== self::VERSION) {
            throw new InvalidArgumentException('Unsupported regulated calculation version.');
        }
        $eligible = (($second['qualified'] ?? ! $second['eliminated']) <=> ($first['qualified'] ?? ! $first['eliminated']));
        if ($eligible !== 0) {
            return $eligible;
        }

        return match ($discipline) {
            'agility' => (($first['total_faults'] ?? $first['penalties']) <=> ($second['total_faults'] ?? $second['penalties']))
                ?: ($first['time'] <=> $second['time'])
                ?: (isset($first['runoff'], $second['runoff'])
                    ? (($first['runoff']['total_faults'] <=> $second['runoff']['total_faults']) ?: ($first['runoff']['time'] <=> $second['runoff']['time'])) : 0),
            'nosework' => ($second['score'] <=> $first['score'])
                ?: (($second['found_all'] ?? false) <=> ($first['found_all'] ?? false))
                ?: ($first['time'] <=> $second['time'])
                ?: (($second['search_tiebreak_score'] ?? 0) <=> ($first['search_tiebreak_score'] ?? 0)),
            'canicross' => $first['time'] <=> $second['time'],
            'conformation', 'progeny' => $second['score'] <=> $first['score'],
            default => throw new InvalidArgumentException('Unknown regulated discipline.'),
        };
    }

    /**
     * @param  array<string,mixed>  $snapshot
     * @param  array<string,mixed>  $plan
     * @param  array<string,mixed>  $rules
     * @param  list<float>  $draws
     * @return array<string,mixed>
     */
    public function simulate(string $discipline, array $snapshot, array $plan, array $rules, array $draws): array
    {
        if ($discipline === 'agility' && ($rules['regulation']['version'] ?? 1) >= 2 && ($rules['regulation']['format'] ?? '') === 'agility_combined') {
            return $this->combinedAgility($snapshot, $plan, $rules, $draws);
        }
        $stageKeys = $rules['stages'] ?? null;
        $decisions = $plan['stages'] ?? null;
        if (($rules['version'] ?? null) !== self::VERSION || ($snapshot['version'] ?? null) !== self::SNAPSHOT_VERSION
            || ! is_array($stageKeys) || ! array_is_list($stageKeys) || count($stageKeys) < 1 || count($stageKeys) > 5
            || ! is_array($decisions) || ! array_is_list($decisions) || count($decisions) !== count($stageKeys)
            || count($draws) < count($stageKeys) * 2 || ! in_array($discipline, ['conformation', 'progeny', 'agility', 'nosework', 'canicross'], true)) {
            throw new InvalidArgumentException('Invalid regulated event simulation.');
        }
        foreach ($draws as $draw) {
            if (! is_finite($draw) || $draw < 0 || $draw > 1) {
                throw new InvalidArgumentException('Event draws must be finite probabilities.');
            }
        }
        if ($discipline === 'agility') {
            $snapshot['modifiers'] = $this->agilityWarmupModifiers($snapshot['gear'] ?? []);
        }
        $preparation = $this->performance->preparation($discipline, $snapshot);
        $simulation = $this->balance['simulation'];
        $fatigue = $preparation['initialFatigue'];
        $focus = $preparation['initialFocus'];
        $stages = [];
        $score = 0.0;
        $elapsed = 0.0;
        $penalties = 0;
        $refusals = 0;
        $qualifiedCategories = 0;
        $foundAll = true;
        foreach ($stageKeys as $index => $key) {
            $decision = $decisions[$index];
            if (! is_string($key) || ! isset($simulation['tactics'][$decision])) {
                throw new InvalidArgumentException('Unknown stage or decision.');
            }
            $tactic = $simulation['tactics'][$decision];
            $quality = $this->performance->stageQuality($discipline, $snapshot['stats'], $snapshot['skills'] ?? [], min(2, $index)) * $preparation['careMultiplier'];
            $chance = max($simulation['minimum_error_probability'], min($simulation['maximum_error_probability'],
                $simulation['base_error_probability'] + $simulation['stage_difficulty'][$index]
                - $quality * $simulation['quality_error_reduction'] - $focus * $simulation['focus_error_reduction']
                + $fatigue * $simulation['fatigue_error_increase'] + $tactic['risk']
                - $preparation['consistencyBonus'] - $preparation['modifiers']['precision']));
            $firstError = $draws[$index * 2] < $chance;
            $secondError = $draws[$index * 2 + 1] < $chance * 0.4;
            $stagePenalties = (int) $firstError + (int) $secondError;
            $duration = max(1.0, ($simulation['base_stage_seconds'] - $quality * $simulation['quality_time_reduction'] + $fatigue * 0.4)
                / max(0.4, $tactic['pace'] * (1 + $preparation['modifiers']['pace'])));
            $stageScore = 0.0;
            $stageQualified = true;
            $note = $stagePenalties > 0 ? 'precision' : 'clear';
            $exterior = null;
            $exteriorContribution = 0.0;
            $presentationContribution = 0.0;
            $extra = [];
            if ($discipline === 'nosework') {
                $nosework = $this->balance['regulations']['nosework'];
                $advanced = in_array($plan['sport_class'] ?? '', ['nw_2', 'nw_3'], true);
                $odorTest = ($rules['regulation']['format'] ?? '') === 'odor_test';
                $secondAlert = $firstError && $secondError;
                $falseAlerts = (int) $firstError + (int) $secondAlert;
                $miss = $secondError && ! $secondAlert;
                $hides = $advanced ? 2 : 1;
                $stageScore = (float) $nosework['category_max_score'] - $falseAlerts * $nosework['false_alert_penalty'] - (int) $miss * $nosework['missed_hide_penalty'];
                if (($advanced && $falseAlerts > 0) || $falseAlerts >= 2 || (! $advanced && $miss) || ($odorTest && ($falseAlerts > 0 || $miss))) {
                    $stageScore = 0.0;
                }
                $stageQualified = $stageScore >= $nosework['category_qualifying_score'];
                $foundAll = $foundAll && ! $miss;
                $extra = ['qualified' => $stageQualified, 'max_score' => $nosework['category_max_score'], 'hides' => $hides, 'found' => $hides - (int) $miss, 'false_alerts' => $falseAlerts];
                $note = $falseAlerts > 0 ? 'false_alert' : ($miss ? 'missed_hide' : 'clear');
            } elseif ($discipline === 'conformation') {
                $value = $snapshot['exterior'][$key] ?? 65;
                if (! is_numeric($value) || ! is_finite((float) $value)) {
                    throw new InvalidArgumentException('Exterior must be finite.');
                }
                $exterior = max(0.0, min(100.0, (float) $value));
                $exteriorContribution = $exterior * $simulation['exterior_weight'];
                $presentationContribution = ($preparation['states']['cleanliness'] + $preparation['states']['health'] + $focus) / 3 * $preparation['careMultiplier'] * $simulation['presentation_weight'];
                $stageScore = max(0.0, min(100.0, $exteriorContribution + $presentationContribution - $stagePenalties * 5));
            } elseif ($discipline === 'progeny') {
                $children = $snapshot['offspring'] ?? [];
                $count = count($children);
                $stageQualified = $count >= $this->balance['regulations']['shows']['progeny_minimum'] && $count <= $this->balance['regulations']['shows']['progeny_maximum'];
                $types = array_map(fn (array $child): float => (float) ($child['exterior']['type'] ?? 65), $children);
                $average = $count > 0 ? array_sum($types) / $count : 0.0;
                $spread = $count > 0 ? array_sum(array_map(fn (float $value): float => abs($value - $average), $types)) / $count : 100.0;
                $titleCount = array_sum(array_map(fn (array $child): int => min(3, count($child['titles'] ?? [])), $children));
                $stageScore = match ($index) {
                    0 => $average, 1 => max(0.0, 100 - $spread * 2), default => $count > 0 ? min(100.0, $titleCount / $count * 25) : 0.0,
                };
                $stagePenalties = 0;
                $duration = 0.0;
                $note = 'progeny';
            } elseif ($discipline === 'agility') {
                $refusals += (int) $secondError;
                $stageScore = -$duration;
            } else {
                $duration += $stagePenalties * 15;
                $stageScore = -$duration;
            }
            $stages[] = [
                'key' => $key, 'decision' => $decision, 'time' => round($duration, 2), 'penalties' => $stagePenalties,
                'score' => round($stageScore, 2), 'fatigue' => round($fatigue, 2), 'focus' => round($focus, 2), 'note' => $note,
                'qualified' => $stageQualified,
                'factors' => ['quality' => round($quality, 4), 'careMultiplier' => $preparation['careMultiplier'],
                    'consistencyBonus' => $preparation['consistencyBonus'],
                    'mistakeChance' => round(1 - (1 - $chance) * (1 - $chance * 0.4), 4),
                    'startFatigue' => round($fatigue, 4), 'startFocus' => round($focus, 4),
                    'exterior' => $exterior, 'exteriorContribution' => round($exteriorContribution, 4), 'presentationContribution' => round($presentationContribution, 4)],
                ...$extra,
            ];
            $score += $stageScore;
            $elapsed += $duration;
            $penalties += $stagePenalties;
            $qualifiedCategories += (int) $stageQualified;
            $fatigue = min(100.0, $fatigue + $tactic['fatigue'] * (1 - $preparation['modifiers']['stamina']));
            $focus = max(0.0, min(100.0, $focus + $tactic['focus'] - $stagePenalties * 6));
        }
        $faults = $discipline === 'agility' ? (float) ($penalties * $this->balance['regulations']['agility']['fault_points']) : (float) $penalties;
        $eliminated = $discipline === 'agility' && ($refusals >= 3 || $elapsed > ($rules['time_limit'] ?? PHP_INT_MAX));
        if ($discipline === 'agility' && ! $eliminated && isset($rules['standard_course_time'])) {
            $faults += max(0.0, $elapsed - $rules['standard_course_time']);
        }
        $grade = null;
        $qualified = ! $eliminated;
        if ($discipline === 'conformation') {
            $score /= count($stages);
            $young = in_array($plan['exhibition_class'] ?? '', ['baby', 'puppy'], true);
            $grade = $young ? 'not_promising' : 'satisfactory';
            foreach ($simulation[$young ? 'young_show_grades' : 'show_grades'] as $label => $threshold) {
                if ($score >= $threshold) {
                    $grade = $label;
                    break;
                }
            }
            $qualified = in_array($grade, ['excellent', 'very_good', 'very_promising', 'promising'], true);
        } elseif ($discipline === 'agility') {
            $agility = $this->balance['regulations']['agility'];
            $grade = $eliminated ? 'eliminated' : ($faults <= $agility['excellent_max_faults'] ? 'excellent' : ($faults <= $agility['very_good_max_faults'] ? 'very_good' : ($faults <= $agility['good_max_faults'] ? 'good' : 'ungraded')));
        } elseif ($discipline === 'nosework' || $discipline === 'progeny') {
            $qualified = $qualifiedCategories === count($stages);
        }
        $scoresByKey = array_column($stages, 'score', 'key');

        return [
            'version' => self::VERSION, 'score' => round($score, 2), 'time' => round($elapsed, 2), 'penalties' => $penalties,
            'total_faults' => round($faults, 2), 'eliminated' => $eliminated, 'qualified' => $qualified, 'grade' => $grade,
            'found_all' => $foundAll, 'qualified_categories' => $qualifiedCategories,
            'search_tiebreak_score' => ($scoresByKey['interior'] ?? 0) + ($scoresByKey[isset($scoresByKey['exterior']) ? 'exterior' : 'objects'] ?? 0),
            'stages' => $stages, 'preparation' => $preparation,
        ];
    }

    /** @param list<Gear> $gear
     * @return array{precision:float,stamina:float,pace:float,focus:float}
     */
    private function agilityWarmupModifiers(array $gear): array
    {
        $modifiers = ['precision' => 0.0, 'stamina' => 0.0, 'pace' => 0.0, 'focus' => 0.0];
        foreach ($gear as $item) {
            if ($item['phase'] !== 'preparation') {
                continue;
            }
            foreach ($modifiers as $metric => $value) {
                $modifiers[$metric] += $item['modifiers'][$metric] ?? 0;
            }
        }

        return $this->performance->modifiers($modifiers);
    }

    /**
     * @param  array<string,mixed>  $snapshot
     * @param  array<string,mixed>  $plan
     * @param  array<string,mixed>  $rules
     * @param  list<float>  $draws
     * @return array<string,mixed>
     */
    private function combinedAgility(array $snapshot, array $plan, array $rules, array $draws): array
    {
        if (($rules['regulation']['system'] ?? 'rkf') !== 'rkf' || ! is_array($rules['stages'] ?? null) || count($rules['stages']) !== 6
            || ! is_array($plan['stages'] ?? null) || ! array_is_list($plan['stages']) || count($plan['stages']) !== 6 || count($draws) < 12) {
            throw new InvalidArgumentException('A national combined event requires two complete courses.');
        }
        $legs = [];
        $stages = [];
        foreach (['agility', 'jumping'] as $index => $format) {
            $leg = $this->simulate('agility', $snapshot, [...$plan, 'stages' => array_slice($plan['stages'], $index * 3, 3)], [
                ...$rules, 'stages' => ['opening', 'technical', 'finish'], 'regulation' => [...$rules['regulation'], 'format' => $format],
            ], array_slice($draws, $index * 6, 6));
            $legs[$format] = $leg;
            foreach ($leg['stages'] as $stage) {
                $stages[] = [...$stage, 'key' => $format.'_'.$stage['key'], 'course' => $format];
            }
        }
        $faults = round($legs['agility']['total_faults'] + $legs['jumping']['total_faults'], 2);
        $time = round($legs['agility']['time'] + $legs['jumping']['time'], 2);
        $eliminated = $legs['agility']['eliminated'] || $legs['jumping']['eliminated'];
        $agility = $this->balance['regulations']['agility'];

        return [
            'version' => self::VERSION, 'score' => -$time, 'time' => $time,
            'penalties' => $legs['agility']['penalties'] + $legs['jumping']['penalties'], 'total_faults' => $faults,
            'eliminated' => $eliminated, 'qualified' => ! $eliminated,
            'grade' => $eliminated ? 'eliminated' : ($faults <= $agility['excellent_max_faults'] ? 'excellent' : ($faults <= $agility['very_good_max_faults'] ? 'very_good' : ($faults <= $agility['good_max_faults'] ? 'good' : 'ungraded'))),
            'legs' => $legs, 'stages' => $stages, 'preparation' => $legs['agility']['preparation'],
        ];
    }
}
