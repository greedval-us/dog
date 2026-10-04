<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\DTO\GameEventProtocol;
use App\Modules\Pets\Enums\GameEventDiscipline;
use InvalidArgumentException;

/**
 * @phpstan-import-type Result from GameEventProtocol
 * @phpstan-import-type Preparation from GameEventProtocol
 */
final class GameEventSimulator
{
    public const VERSION = 2;

    public const SNAPSHOT_VERSION = 1;

    public function __construct(private GameEventPerformance $performance = new GameEventPerformance) {}

    /**
     * @param  array{eliminated:bool, penalties:int, score:float, time:float}  $first
     * @param  array{eliminated:bool, penalties:int, score:float, time:float}  $second
     */
    public function compareResults(string $discipline, array $first, array $second, int $version = self::VERSION): int
    {
        if ($version !== self::VERSION) {
            throw new InvalidArgumentException('Unsupported event calculation version.');
        }
        $kind = $this->discipline($discipline);
        $comparison = ($first['eliminated'] <=> $second['eliminated'])
            ?: ($kind->ranksByPenalties() ? ($first['penalties'] <=> $second['penalties']) : ($second['score'] <=> $first['score']));

        return $comparison ?: ($kind->isExhibition() ? 0 : ($first['time'] <=> $second['time']));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $rules
     * @param  list<float>  $draws
     * @return Result
     */
    public function simulate(string $discipline, array $snapshot, array $plan, array $rules, array $draws): array
    {
        $kind = $this->discipline($discipline);
        $protocol = GameEventProtocol::fromArrays($discipline, $snapshot, $rules);
        if (($rules['version'] ?? null) !== self::VERSION
            || $protocol->snapshotVersion !== self::SNAPSHOT_VERSION
            || ! $this->hasThreeStages($rules['stages'] ?? null) || ! $this->hasThreeStages($plan['stages'] ?? null)
            || ! is_array($snapshot['stats'] ?? null) || ! is_array($snapshot['states'] ?? null) || count($draws) < 6) {
            throw new InvalidArgumentException('Unsupported version-two event simulation.');
        }
        foreach ($draws as $draw) {
            if (! is_finite($draw) || $draw < 0 || $draw > 1) {
                throw new InvalidArgumentException('Event draws must be finite probabilities.');
            }
        }
        $preparation = $this->performance->preparation($discipline, $snapshot);
        if ($kind->isDocumentary()) {
            return $this->progeny($snapshot, $plan, $rules, $preparation);
        }
        $states = $preparation['states'];
        $modifiers = $preparation['modifiers'];
        $focus = $preparation['initialFocus'];
        $fatigue = $preparation['initialFatigue'];
        $careMultiplier = $preparation['careMultiplier'];
        $consistency = min(0.08, max(0, $snapshot['career_experience'] ?? 0) * 0.001);
        $totalTime = 0.0;
        $totalPenalties = 0;
        $totalScore = 0.0;
        $falseAlert = false;
        $stages = [];

        foreach ($rules['stages'] as $index => $key) {
            $decision = $plan['stages'][$index];
            if (! in_array($decision, ['careful', 'balanced', 'bold'], true)) {
                throw new InvalidArgumentException('Unknown stage decision.');
            }
            $startFatigue = $fatigue;
            $startFocus = $focus;
            $risk = match ($decision) {
                'careful' => -0.14, 'bold' => 0.12, default => 0.0,
            };
            $pace = match ($decision) {
                'careful' => 0.9, 'bold' => 1.15, default => 1.0,
            };
            $pace *= 1 + $modifiers['pace'];
            $quality = $this->performance->stageQuality($discipline, $snapshot['stats'], $snapshot['skills'] ?? [], $index) * $careMultiplier;
            $difficulty = [0.04, 0.12, 0.08][$index] + ($discipline === 'nosework' && $index === 2 ? 0.06 : 0);
            $chance = max(0.02, min(0.8, 0.46 + $difficulty - $quality * 0.003 - $focus * 0.002 + $fatigue * 0.004 + $risk - $consistency - $modifiers['precision']));
            $mistakeChance = $discipline === 'nosework'
                ? 1 - (1 - $chance * 0.25) * (1 - $chance)
                : 1 - (1 - $chance) * (1 - $chance * 0.4);
            $penalties = (int) ($draws[$index * 2] < $chance) + (int) ($draws[$index * 2 + 1] < $chance * 0.4);
            $time = max(1.0, (100 - $quality * 0.55 + $fatigue * ($discipline === 'canicross' ? 1.0 : 0.4)) / max(0.4, $pace));
            $note = $penalties > 0 ? ($fatigue > 50 ? 'fatigue' : 'precision') : ($decision === 'careful' ? 'controlled' : 'clear');
            $exterior = null;
            $exteriorContribution = 0.0;
            $presentationContribution = 0.0;
            if ($discipline === 'nosework') {
                $alert = $draws[$index * 2] < $chance * 0.25;
                $miss = $draws[$index * 2 + 1] < $chance;
                $falseAlert = $falseAlert || $alert;
                $penalties = (int) $alert + (int) $miss;
                $note = $alert ? 'false_alert' : ($miss ? 'missed_hide' : 'clear');
                $score = max(0, 100 - $penalties * 35) - $time * 0.05;
            } elseif ($kind->isExhibition()) {
                $inheritedQuality = (float) ($snapshot['exterior'][['type', 'structure', 'movement'][$index]] ?? 65);
                if (! is_finite($inheritedQuality)) {
                    throw new InvalidArgumentException('Event exterior must be finite.');
                }
                $exterior = max(0, min(100, $inheritedQuality));
                $exteriorContribution = $exterior * 0.8;
                $presentationContribution = ($states['cleanliness'] + $states['health'] + $focus) / 3 * $careMultiplier * 0.2;
                $score = $exteriorContribution + $presentationContribution
                    + ($decision === 'bold' ? 5 : ($decision === 'careful' ? 1 : 3)) - $penalties * 12;
            } else {
                $time += $penalties * ($discipline === 'agility' ? 5 : 15);
                $score = -$time;
            }
            $fatigue = min(100, $fatigue + match ($decision) {
                'careful' => 8, 'bold' => 22, default => 13,
            } * (1 - $modifiers['stamina']));
            $focus = max(0, min(100, $focus + match ($decision) {
                'careful' => 7, 'bold' => -9, default => -2,
            } - $penalties * 6));
            $stages[] = [
                'key' => $key, 'decision' => $decision, 'time' => round($time, 2), 'penalties' => $penalties,
                'score' => round($score, 2), 'fatigue' => round($fatigue, 2), 'focus' => round($focus, 2), 'note' => $note,
                'factors' => [
                    'quality' => round($quality, 4), 'careMultiplier' => round($careMultiplier, 4), 'mistakeChance' => round($mistakeChance, 4),
                    'startFatigue' => round($startFatigue, 4), 'startFocus' => round($startFocus, 4), 'exterior' => $exterior,
                    'exteriorContribution' => round($exteriorContribution, 4), 'presentationContribution' => round($presentationContribution, 4),
                ],
            ];
            $totalTime += $time;
            $totalScore += $score;
            $totalPenalties += $penalties;
        }
        $eliminated = ($discipline === 'agility' && $totalPenalties >= 5)
            || ($discipline === 'nosework' && $falseAlert)
            || ($kind->ranksByPenalties() && $totalTime > ($rules['time_limit'] ?? 360));

        return [
            'version' => self::VERSION, 'score' => round($totalScore, 2), 'time' => round($totalTime, 2), 'penalties' => $totalPenalties,
            'eliminated' => $eliminated, 'stages' => $stages, 'preparation' => $preparation,
        ];
    }

    private function discipline(string $discipline): GameEventDiscipline
    {
        return GameEventDiscipline::tryFrom($discipline) ?? throw new InvalidArgumentException('Unsupported version-two discipline.');
    }

    /**
     * Documentary judging uses actual descendants and never the parent's current physical preparation.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $rules
     * @param  Preparation  $preparation
     * @return Result
     */
    private function progeny(array $snapshot, array $plan, array $rules, array $preparation): array
    {
        $offspring = $snapshot['offspring'] ?? [];
        $scores = [0.0, 0.0, 0.0];
        if ($offspring !== []) {
            $types = array_map(fn (array $child): float => (float) ($child['exterior']['type'] ?? 65), $offspring);
            $average = array_sum($types) / count($types);
            $spread = array_sum(array_map(fn (float $type): float => abs($type - $average), $types)) / count($types);
            $titles = array_sum(array_map(fn (array $child): int => min(3, count($child['titles'] ?? [])), $offspring));
            $scores = [$average, max(0, 100 - $spread * 2), min(100, $titles / count($offspring) * 25)];
        }
        $stages = [];
        foreach ($rules['stages'] as $index => $key) {
            $decision = $plan['stages'][$index];
            if (! in_array($decision, ['careful', 'balanced', 'bold'], true)) {
                throw new InvalidArgumentException('Unknown stage decision.');
            }
            $stages[] = [
                'key' => $key, 'decision' => $decision, 'time' => 0.0, 'penalties' => 0,
                'score' => round($scores[$index], 2), 'fatigue' => 0.0, 'focus' => 100.0, 'note' => 'progeny',
                'factors' => [
                    'quality' => round($scores[$index], 4), 'careMultiplier' => 1.0, 'mistakeChance' => 0.0,
                    'startFatigue' => 0.0, 'startFocus' => 100.0, 'exterior' => null,
                    'exteriorContribution' => 0.0, 'presentationContribution' => 0.0,
                ],
            ];
        }

        return ['version' => self::VERSION, 'score' => round(array_sum($scores), 2), 'time' => 0.0,
            'penalties' => 0, 'eliminated' => false, 'stages' => $stages, 'preparation' => $preparation];
    }

    private function hasThreeStages(mixed $stages): bool
    {
        return is_array($stages) && array_is_list($stages) && count($stages) === 3
            && count(array_filter($stages, fn (mixed $stage): bool => is_string($stage) && $stage !== '')) === 3;
    }
}
