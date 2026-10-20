<?php

namespace App\Modules\Pets\Calculators;

use App\Modules\Pets\Enums\ExhibitionClass;
use InvalidArgumentException;

final class RegulatedEventRules
{
    /** @param array<string, mixed> $balance */
    public function __construct(private array $balance) {}

    /**
     * @param  array<string, mixed>  $regulation
     * @param  array<string, mixed>  $profile
     * @return list<array{key:string,labelKey:string,plan:array<string,mixed>,categoryKey:string,eligible:bool,reason:?string}>
     */
    public function entryOptions(string $discipline, array $regulation, array $profile, int $ageMonths): array
    {
        $plans = match ($discipline) {
            'conformation' => array_map(fn (ExhibitionClass $class): array => ['exhibition_class' => $class->value], ExhibitionClass::cases()),
            'progeny' => [[]],
            'agility' => array_map(fn (int $level): array => ['sport_class' => 'agility_'.$level], [1, 2, 3]),
            'canicross' => [['sport_class' => 'canicross_open', 'handler_category' => 'open']],
            'nosework' => $this->noseworkPlans($regulation),
            default => throw new InvalidArgumentException('Unknown regulated event discipline.'),
        };
        $options = [];
        foreach ($plans as $plan) {
            $plan['stages'] = array_fill(0, count($this->stages($discipline, $regulation, $plan)), 'balanced');
            $key = $plan['exhibition_class'] ?? $plan['sport_class'] ?? 'progeny';
            if (isset($plan['program'])) {
                $key .= ':'.$plan['program'].(isset($plan['test_type']) ? ':'.$plan['test_type'] : '');
            }
            $reason = $this->ineligibility($discipline, $regulation, $profile, $ageMonths, $plan);
            $options[] = ['key' => $key, 'labelKey' => 'events.regulations.classes.'.($plan['exhibition_class'] ?? $plan['sport_class'] ?? 'progeny'),
                'plan' => $plan, 'categoryKey' => $this->division($discipline, $regulation, $profile, $plan), 'eligible' => $reason === null, 'reason' => $reason];
        }

        return $options;
    }

    /**
     * @param  array<string,mixed>  $regulation
     * @param  array<string,mixed>  $plan
     * @return list<string>
     */
    public function stages(string $discipline, array $regulation, array $plan): array
    {
        return match ($discipline) {
            'conformation' => ['type', 'structure', 'movement'],
            'progeny' => ['type', 'uniformity', 'achievements'],
            'agility' => ($regulation['version'] ?? 1) >= 2 && ($regulation['format'] ?? '') === 'agility_combined' && ($regulation['system'] ?? 'rkf') === 'rkf'
                ? ['agility_opening', 'agility_technical', 'agility_finish', 'jumping_opening', 'jumping_technical', 'jumping_finish']
                : ['opening', 'technical', 'finish'],
            'canicross' => ['start', 'course', 'finish'],
            'nosework' => ($regulation['format'] ?? '') === 'odor_test'
                ? ['odor_test']
                : $this->balance['regulations']['nosework'][in_array($regulation['rank'] ?? '', ['certificate', 'championship', 'cup'], true) ? 'certificate_categories' : 'regular_categories'],
            default => throw new InvalidArgumentException('Unknown regulated event discipline.'),
        };
    }

    /**
     * @param  array<string,mixed>  $regulation
     * @param  array<string,mixed>  $plan
     */
    public function minimumMonths(string $discipline, array $regulation, array $plan): int
    {
        return match ($discipline) {
            'agility' => $this->balance['regulations']['agility'][($regulation['system'] ?? 'rkf') === 'fci' ? 'international_months' : 'national_months'],
            'canicross' => $this->balance['regulations']['canicross']['minimum_months'],
            'nosework' => $this->balance['regulations']['nosework']['minimum_months'][$plan['sport_class'] ?? 'nw_0'] ?? PHP_INT_MAX,
            'conformation' => match ($plan['exhibition_class'] ?? '') {
                '', 'baby' => 3, 'puppy' => 6, 'junior' => 9, 'veteran' => 96, default => 15,
            },
            'progeny' => 15,
            default => PHP_INT_MAX,
        };
    }

    /**
     * @param  array<string,mixed>  $regulation
     * @param  array<string,mixed>  $profile
     * @param  array<string,mixed>  $plan
     */
    public function validPlan(string $discipline, array $regulation, array $profile, int $ageMonths, array $plan): bool
    {
        $allowed = match ($discipline) {
            'conformation' => ['stages', 'exhibition_class'],
            'progeny' => ['stages', 'offspring_ids'],
            'agility' => ['stages', 'sport_class'],
            'nosework' => ($regulation['format'] ?? '') === 'odor_test'
                ? ['stages', 'sport_class', 'program', 'test_type']
                : ['stages', 'sport_class', 'program'],
            'canicross' => ['stages', 'sport_class', 'handler_category'],
            default => [],
        };
        if ($allowed === [] || array_diff(array_keys($plan), $allowed) !== []) {
            return false;
        }
        if ($discipline === 'conformation' && ! is_string($plan['exhibition_class'] ?? null)) {
            return false;
        }
        if (in_array($discipline, ['agility', 'nosework', 'canicross'], true) && ! is_string($plan['sport_class'] ?? null)) {
            return false;
        }
        if ($discipline === 'nosework' && (! is_string($plan['program'] ?? null)
            || (($regulation['format'] ?? '') === 'odor_test' && ! is_string($plan['test_type'] ?? null)))) {
            return false;
        }
        if ($discipline === 'canicross' && isset($plan['handler_category']) && ! is_string($plan['handler_category'])) {
            return false;
        }
        if ($discipline === 'progeny') {
            $offspring = $plan['offspring_ids'] ?? null;
            if (! is_array($offspring) || ! array_is_list($offspring)
                || count($offspring) < $this->balance['regulations']['shows']['progeny_minimum']
                || count($offspring) > $this->balance['regulations']['shows']['progeny_maximum']
                || count(array_filter($offspring, fn (mixed $id): bool => is_int($id) && $id > 0)) !== count($offspring)
                || count(array_unique($offspring)) !== count($offspring)) {
                return false;
            }
        }
        $decisions = $plan['stages'] ?? null;
        if (! is_array($decisions) || ! array_is_list($decisions)
            || count($decisions) !== count($this->stages($discipline, $regulation, $plan))
            || count(array_filter($decisions, fn (mixed $decision): bool => is_string($decision) && in_array($decision, ['careful', 'balanced', 'bold'], true))) !== count($decisions)) {
            return false;
        }

        return $this->ineligibility($discipline, $regulation, $profile, $ageMonths, $plan) === null;
    }

    /**
     * @param  array<string,mixed>  $regulation
     * @param  array<string,mixed>  $profile
     * @param  array<string,mixed>  $plan
     */
    public function division(string $discipline, array $regulation, array $profile, array $plan): string
    {
        return match ($discipline) {
            'conformation' => ($plan['exhibition_class'] ?? 'open').':breed-'.($profile['breed_id'] ?? 0).':'.($profile['sex'] ?? 'male'),
            'progeny' => 'progeny:breed-'.($profile['breed_id'] ?? 0),
            'agility' => ($plan['sport_class'] ?? 'agility_1').':'.$this->heightCategory($profile),
            'nosework' => ($plan['sport_class'] ?? 'nw_0').':'.($plan['program'] ?? 'pr1').(isset($plan['test_type']) ? ':'.$plan['test_type'] : ''),
            'canicross' => 'canicross_open:'.(($regulation['version'] ?? 1) >= 2 && ($regulation['sportScope'] ?? '') === 'breed'
                ? 'breed-'.($profile['breed_id'] ?? 0) : ($profile['race_category'] ?? '4')).':'.($plan['handler_category'] ?? 'open'),
            default => throw new InvalidArgumentException('Unknown regulated event discipline.'),
        };
    }

    /** @param array<string,mixed> $profile */
    private function heightCategory(array $profile): string
    {
        if (is_numeric($profile['height_cm'] ?? null) && $profile['height_cm'] > 0) {
            foreach ($this->balance['regulations']['agility']['height_boundaries'] as $category => $height) {
                if ($profile['height_cm'] < $height) {
                    return $category;
                }
            }

            return 'L';
        }

        return in_array($profile['height_category'] ?? '', ['S', 'M', 'I', 'L'], true) ? $profile['height_category'] : 'L';
    }

    /**
     * @param  array<string,mixed>  $regulation
     * @return list<array<string,mixed>>
     */
    private function noseworkPlans(array $regulation): array
    {
        $plans = [];
        foreach ($this->balance['regulations']['nosework']['odors'] as $program => $odors) {
            if (($regulation['format'] ?? '') === 'odor_test') {
                foreach ($odors as $odor) {
                    $plans[] = ['sport_class' => 'nw_test', 'program' => $program, 'test_type' => $odor];
                }
            } else {
                foreach (['nw_0', 'nw_1', 'nw_2', 'nw_3', 'nw_v'] as $class) {
                    $plans[] = ['sport_class' => $class, 'program' => $program];
                }
            }
        }

        return $plans;
    }

    /**
     * @param  array<string,mixed>  $regulation
     * @param  array<string,mixed>  $profile
     * @param  array<string,mixed>  $plan
     */
    private function ineligibility(string $discipline, array $regulation, array $profile, int $ageMonths, array $plan): ?string
    {
        $reason = 'events.regulations.unavailable.';
        if (($regulation['version'] ?? 1) >= 2 && isset($regulation['breedId']) && $regulation['breedId'] !== ($profile['breed_id'] ?? null)) {
            return $reason.'breed';
        }
        if ($ageMonths < $this->minimumMonths($discipline, $regulation, $plan)) {
            return $reason.'age';
        }
        if ($discipline === 'conformation' || $discipline === 'progeny') {
            $recognized = $profile[($regulation['system'] ?? 'rkf') === 'fci' ? 'recognized_fci' : 'recognized_rkf'] ?? false;
            if (! $recognized || ! ($profile['registered'] ?? false)) {
                return $reason.'registry';
            }
            if (isset($regulation['breedId']) && $regulation['breedId'] !== ($profile['breed_id'] ?? null)) {
                return $reason.'breed';
            }
            if ($discipline === 'progeny') {
                return null;
            }
            $class = ExhibitionClass::tryFrom($plan['exhibition_class'] ?? '');
            if ($class === null || ! $class->includes($ageMonths)) {
                return $reason.'age';
            }
            if ($class === ExhibitionClass::Working && (! ($profile['working_breed'] ?? false) || ! ($profile['working_certificate'] ?? false))) {
                return $reason.'working_certificate';
            }
            if ($class === ExhibitionClass::Champion && array_intersect($profile['championships'] ?? [], ['CH_RUS', 'CIB', 'CIE']) === []) {
                return $reason.'champion';
            }
            if ($class === ExhibitionClass::ClubChampion && (($regulation['rank'] ?? '') !== 'specialty' || ! in_array('CH_CLUB', $profile['championships'] ?? [], true))) {
                return $reason.'champion';
            }
        } elseif ($discipline === 'agility') {
            $level = (int) ($profile['agility_level'] ?? 1);
            $available = $profile['agility_available_levels'] ?? [$level];
            $class = $plan['sport_class'] ?? '';
            if (! in_array($class, array_map(fn (int $value): string => 'agility_'.$value, $available), true)) {
                return $reason.'qualification';
            }
            if (($regulation['system'] ?? '') === 'fci' && (! ($profile['recognized_fci'] ?? false) || ! ($profile['registered'] ?? false))) {
                return $reason.'registry';
            }
        } elseif ($discipline === 'nosework') {
            $class = $plan['sport_class'] ?? '';
            $program = $plan['program'] ?? '';
            $odors = $this->balance['regulations']['nosework']['odors'][$program] ?? null;
            if ($odors === null) {
                return $reason.'program';
            }
            if (($regulation['format'] ?? '') === 'odor_test') {
                return $class === 'nw_test' && in_array($plan['test_type'] ?? '', $odors, true) ? null : $reason.'odor';
            }
            $required = $this->balance['regulations']['nosework']['required_odors'][$class] ?? null;
            if ($required === null || count(array_intersect(array_slice($odors, 0, $required), $profile['nosework_tests'][$program] ?? [])) !== $required) {
                return $reason.'odor';
            }
            $level = (int) ($profile['nosework_levels'][$program] ?? 0);
            if ($class === 'nw_v') {
                if (($profile['nosework_nw0_qualifications'][$program] ?? 0) < 2) {
                    return $reason.'qualification';
                }
            } elseif ($class !== 'nw_'.$level) {
                return $reason.'qualification';
            }
        } elseif ($discipline === 'canicross') {
            if (($plan['sport_class'] ?? '') !== 'canicross_open' || ($plan['handler_category'] ?? 'open') !== 'open') {
                return $reason.'class';
            }
            if (($regulation['system'] ?? 'rkf') !== 'rkf') {
                return $reason.'unsupported_format';
            }
        }

        return null;
    }
}
