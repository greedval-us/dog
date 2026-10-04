<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\DTO\GameEventProtocol;
use App\Modules\Pets\Enums\GameEventDiscipline;
use InvalidArgumentException;

/** @phpstan-import-type Result from GameEventProtocol */
final class GameEventSimulatorV1
{
    public const VERSION = 1;

    public const SNAPSHOT_VERSION = 1;

    private const DISCIPLINES = [GameEventDiscipline::Agility, GameEventDiscipline::Nosework, GameEventDiscipline::Canicross, GameEventDiscipline::Conformation, GameEventDiscipline::Progeny];

    /**
     * @param  array{eliminated:bool, penalties:int, score:float, time:float}  $first
     * @param  array{eliminated:bool, penalties:int, score:float, time:float}  $second
     */
    public function compareResults(string $discipline, array $first, array $second): int
    {
        $kind = $this->discipline($discipline);

        return ($first['eliminated'] <=> $second['eliminated'])
            ?: ($kind->ranksByPenalties()
                ? ($first['penalties'] <=> $second['penalties'])
                : ($second['score'] <=> $first['score']))
            ?: ($first['time'] <=> $second['time']);
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
        if (($rules['version'] ?? 0) !== self::VERSION
            || ! $this->hasThreeStages($rules['stages'] ?? null) || ! $this->hasThreeStages($plan['stages'] ?? null)
            || ! is_array($snapshot['stats'] ?? null) || ! is_array($snapshot['states'] ?? null)
            || count($draws) < 6) {
            throw new InvalidArgumentException('Unsupported event simulation.');
        }
        foreach ($draws as $draw) {
            if (! is_finite($draw) || $draw < 0 || $draw > 1) {
                throw new InvalidArgumentException('Event draws must be finite probabilities.');
            }
        }
        $stats = $snapshot['stats'];
        $states = $snapshot['states'];
        $modifiers = $snapshot['modifiers'] ?? [];
        $quality = match ($discipline) {
            'agility' => $this->stat($stats, 'agility') * 0.35 + $this->stat($stats, 'speed') * 0.25 + $this->stat($stats, 'obedience') * 0.2 + $this->stat($stats, 'intelligence') * 0.2,
            'nosework' => $this->stat($stats, 'intelligence') * 0.45 + $this->stat($stats, 'obedience') * 0.35 + $this->stat($stats, 'endurance') * 0.2 + min(10, (($snapshot['skills']['keen_nose'] ?? 0) + ($snapshot['skills']['search'] ?? 0)) * 2),
            'canicross' => $this->stat($stats, 'endurance') * 0.45 + $this->stat($stats, 'speed') * 0.35 + $this->stat($stats, 'strength') * 0.2,
            default => $this->stat($stats, 'obedience'),
        };
        $focus = min(100, max(0, (($states['bond'] ?? 0) + ($states['mood'] ?? 0) + $this->stat($stats, 'obedience')) / 3 + ($modifiers['focus'] ?? 0) * 100));
        $fatigue = max(0, 100 - ($states['energy'] ?? 100)) * 0.35;
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
            $risk = match ($decision) {
                'careful' => -0.14, 'bold' => 0.12, default => 0.0
            };
            $pace = match ($decision) {
                'careful' => 0.9, 'bold' => 1.15, default => 1.0
            };
            $pace *= 1 + ($modifiers['pace'] ?? 0);
            $stageQuality = $this->stageQuality($discipline, $stats, $snapshot['skills'] ?? [], $index, $quality);
            $difficulty = [0.04, 0.12, 0.08][$index];
            if ($discipline === 'nosework' && $index === 2) {
                $difficulty += 0.06;
            }
            $chance = $discipline === 'progeny' ? 0.0 : max(0.02, min(0.8, 0.46 + $difficulty - $stageQuality * 0.003 - $focus * 0.002 + $fatigue * 0.004 + $risk - $consistency - ($modifiers['precision'] ?? 0)));
            $penalties = (int) ($draws[$index * 2] < $chance) + (int) ($draws[$index * 2 + 1] < $chance * 0.4);
            $time = $discipline === 'progeny' ? 0.0 : max(1.0, (100 - $stageQuality * 0.55 + $fatigue * ($discipline === 'canicross' ? 1.0 : 0.4)) / max(0.4, $pace));
            $score = 0.0;
            $note = $penalties > 0 ? ($fatigue > 50 ? 'fatigue' : 'precision') : ($decision === 'careful' ? 'controlled' : 'clear');
            if ($discipline === 'nosework') {
                $alert = $draws[$index * 2] < $chance * 0.25;
                $miss = $draws[$index * 2 + 1] < $chance;
                $falseAlert = $falseAlert || $alert;
                $penalties = (int) $alert + (int) $miss;
                $note = $alert ? 'false_alert' : ($miss ? 'missed_hide' : 'clear');
                $score = max(0, 100 - $penalties * 35) - $time * 0.05;
            } elseif ($kind->isExhibition()) {
                $base = $this->showQuality($discipline, $snapshot, $index);
                $presentation = $discipline === 'progeny' ? 0 : (($states['cleanliness'] ?? 0) + ($states['health'] ?? 0) + $focus) / 3;
                $score = $discipline === 'progeny' ? $base : $base * 0.8 + $presentation * 0.2
                    + ($decision === 'bold' ? 5 : ($decision === 'careful' ? 1 : 3)) - $penalties * 12;
            } else {
                $time += $penalties * ($discipline === 'agility' ? 5 : 15);
                $score = -$time;
            }
            $fatigue = $discipline === 'progeny' ? 0.0 : min(100, $fatigue + match ($decision) {
                'careful' => 8, 'bold' => 22, default => 13
            } * (1 - ($modifiers['stamina'] ?? 0)));
            $focus = $discipline === 'progeny' ? 100.0 : max(0, min(100, $focus + match ($decision) {
                'careful' => 7, 'bold' => -9, default => -2
            } - $penalties * 6));
            $stages[] = [
                'key' => $key, 'decision' => $decision, 'time' => round($time, 2), 'penalties' => $penalties,
                'score' => round($score, 2), 'fatigue' => round($fatigue, 2), 'focus' => round($focus, 2),
                'note' => $discipline === 'progeny' ? 'progeny' : $note,
            ];
            $totalTime += $time;
            $totalScore += $score;
            $totalPenalties += $penalties;
        }
        $eliminated = ($discipline === 'agility' && $totalPenalties >= 5)
            || ($discipline === 'nosework' && $falseAlert)
            || ($kind->ranksByPenalties() && $totalTime > ($rules['time_limit'] ?? 360));

        return ['version' => self::VERSION, 'score' => round($totalScore, 2), 'time' => round($totalTime, 2), 'penalties' => $totalPenalties, 'eliminated' => $eliminated, 'stages' => $stages];
    }

    private function hasThreeStages(mixed $stages): bool
    {
        return is_array($stages) && array_is_list($stages) && count($stages) === 3
            && count(array_filter($stages, fn (mixed $stage): bool => is_string($stage) && $stage !== '')) === 3;
    }

    private function discipline(string $discipline): GameEventDiscipline
    {
        $kind = GameEventDiscipline::tryFrom($discipline);
        if ($kind === null || ! in_array($kind, self::DISCIPLINES, true)) {
            throw new InvalidArgumentException('Unsupported version-one discipline.');
        }

        return $kind;
    }

    /** @param array<string, int> $stats */
    private function stat(array $stats, string $key): float
    {
        $value = max(0, $stats[$key] ?? 0);

        return 100 * $value / ($value + 70);
    }

    /**
     * @param  array<string, int>  $stats
     * @param  array<string, int>  $skills
     */
    private function stageQuality(string $discipline, array $stats, array $skills, int $index, float $fallback): float
    {
        return match ($discipline.':'.$index) {
            'agility:0' => $this->stat($stats, 'speed') * 0.6 + $this->stat($stats, 'agility') * 0.4,
            'agility:1' => $this->stat($stats, 'agility') * 0.45 + $this->stat($stats, 'obedience') * 0.35 + $this->stat($stats, 'intelligence') * 0.2,
            'agility:2' => $this->stat($stats, 'endurance') * 0.35 + $this->stat($stats, 'speed') * 0.45 + $this->stat($stats, 'agility') * 0.2,
            'canicross:0' => $this->stat($stats, 'endurance') * 0.45 + $this->stat($stats, 'strength') * 0.45 + $this->stat($stats, 'speed') * 0.1,
            'canicross:1' => $this->stat($stats, 'obedience') * 0.5 + $this->stat($stats, 'agility') * 0.3 + $this->stat($stats, 'speed') * 0.2,
            'canicross:2' => $this->stat($stats, 'endurance') * 0.6 + $this->stat($stats, 'speed') * 0.4,
            'nosework:0' => $this->stat($stats, 'intelligence') * 0.6 + $this->stat($stats, 'obedience') * 0.4 + min(10, ($skills['keen_nose'] ?? 0) * 2),
            'nosework:1' => $this->stat($stats, 'intelligence') * 0.5 + $this->stat($stats, 'obedience') * 0.5 + min(10, ($skills['search'] ?? 0) * 2),
            'nosework:2' => $this->stat($stats, 'intelligence') * 0.6 + $this->stat($stats, 'endurance') * 0.4 + min(10, ($skills['keen_nose'] ?? 0) * 2),
            default => $fallback,
        };
    }

    /** @param array<string, mixed> $snapshot */
    private function showQuality(string $discipline, array $snapshot, int $index): float
    {
        $keys = ['type', 'structure', 'movement'];
        if ($discipline === 'conformation') {
            return max(0, min(100, $snapshot['exterior'][$keys[$index]] ?? 65));
        }
        $offspring = $snapshot['offspring'] ?? [];
        if ($offspring === []) {
            return 0;
        }
        $types = array_map(fn (array $child): float => (float) ($child['exterior']['type'] ?? 65), $offspring);
        $average = array_sum($types) / count($types);
        if ($index === 0) {
            return $average;
        }
        if ($index === 1) {
            $spread = array_sum(array_map(fn (float $type): float => abs($type - $average), $types)) / count($types);

            return max(0, 100 - $spread * 2);
        }
        $titles = array_sum(array_map(fn (array $child): int => min(3, count($child['titles'] ?? [])), $offspring));

        return min(100, $titles / count($offspring) * 25);
    }
}
