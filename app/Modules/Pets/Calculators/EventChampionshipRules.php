<?php

namespace App\Modules\Pets\Calculators;

final class EventChampionshipRules
{
    /** @param array<string,mixed> $balance */
    public function __construct(private array $balance) {}

    /**
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $credentials
     * @return list<array<string,mixed>>
     */
    public function calculate(string $discipline, array $profile, array $credentials, int $at): array
    {
        $history = array_values(array_filter($credentials, fn (array $credential): bool => ($credential['discipline'] ?? '') === $discipline
            && ($credential['awarded_at'] ?? 0) <= $at));
        usort($history, fn (array $first, array $second): int => $first['awarded_at'] <=> $second['awarded_at']);
        $held = $profile['championships'] ?? [];
        $awards = [];
        $registered = ($profile['registered'] ?? false) && ($profile['recognized_rkf'] ?? false);
        $fullPedigree = $registered && ($profile['pedigree_complete'] ?? false);
        if ($discipline === 'conformation' && $fullPedigree) {
            $national = array_values(array_filter($history, fn (array $credential): bool => ($credential['awarded_at'] ?? 0) >= $at - $this->balance['regulations']['certificate_validity_months'] * PetAgeRules::SECONDS_PER_MONTH));
            foreach (['JCH_RUS' => ['JCAC', 3], 'VCH_RUS' => ['VCAC', 3]] as $title => [$certificate, $count]) {
                $credits = $this->credits($national, $certificate, $title);
                $this->grant($awards, $held, $title, $credits, $this->meets($credits, $count, $count));
            }
            $cac = $this->credits($national, 'CAC', 'CH_RUS');
            $year = $this->balance['regulations']['championship_interval_months'] * PetAgeRules::SECONDS_PER_MONTH;
            $allSpecial = array_values(array_filter($cac, fn (array $credential): bool => in_array($credential['rank'] ?? '', ['cacib', 'cac_rkf_special'], true)));
            $adultEligible = $this->meets($cac, 4, 4)
                && (in_array('JCH_RUS', [...$held, ...array_column($awards, 'code')], true) || $this->span($cac) >= $year || $this->meets($allSpecial, 4, 4) || $this->meets($cac, 6, 6));
            $this->grant($awards, $held, 'CH_RUS', $cac, $adultEligible);
            foreach (['CH_RKF' => 'CH_RKF_CERT', 'JCH_RKF' => 'JCH_RKF_CERT', 'VCH_RKF' => 'VCH_RKF_CERT'] as $title => $certificate) {
                $receipts = $this->withCode($national, $certificate);
                $this->grant($awards, $held, $title, $receipts, $receipts !== []);
            }
            foreach (['CH_CLUB' => ['KCHK', 4, 2], 'JCH_CLUB' => ['JKCHK', 3, 1], 'VCH_CLUB' => ['VKCHK', 3, 1]] as $title => [$certificate, $count, $monobreed]) {
                $receipts = $this->withCode($national, $certificate);
                $specialties = array_filter($receipts, fn (array $credential): bool => ($credential['rank'] ?? '') === 'specialty');
                $working = $title !== 'CH_CLUB' || ! ($profile['working_breed'] ?? false) || ($profile['working_certificate'] ?? false);
                $this->grant($awards, $held, $title, $receipts, $working && $this->meets($receipts, $count, $count) && count($specialties) >= $monobreed);
            }
            $available = [...$held, ...array_column($awards, 'code')];
            foreach (['GRAND_CH_RUS' => ['CH_RUS', 'CH_CLUB'], 'JGRAND_CH_RUS' => ['JCH_RUS', 'JCH_CLUB'], 'VGRAND_CH_RUS' => ['VCH_RUS', 'VCH_CLUB']] as $title => $required) {
                $this->grant($awards, $held, $title, $national, count(array_intersect($required, $available)) === count($required));
            }
        }
        if ($discipline === 'conformation' && ($profile['recognized_fci'] ?? false) && ($profile['registered'] ?? false) && ($profile['pedigree_complete'] ?? false)) {
            foreach (['CIB_J' => 'CACIB_J', 'CIB_V' => 'CACIB_V'] as $title => $certificate) {
                $receipts = $this->withCode($history, $certificate);
                $this->grant($awards, $held, $title, $receipts, $this->meets($receipts, 3, 3, 3));
            }
            $receipts = $this->withCode($history, 'CACIB');
            foreach ($this->withCode($history, 'R_CACIB') as $reserve) {
                if (in_array('CIB', $reserve['details']['winner_championships'] ?? [], true)) {
                    $receipts[] = $reserve;
                }
            }
            $working = $profile['working_breed'] ?? false;
            $interval = $this->span($receipts) >= $this->balance['regulations']['championship_interval_months'] * PetAgeRules::SECONDS_PER_MONTH;
            $this->grant($awards, $held, 'CIB', $receipts, $interval && ($working
                ? (($profile['working_certificate'] ?? false) && $this->meets($receipts, 2, 2, 2))
                : $this->meets($receipts, 4, 3, 3)));
            $this->grant($awards, $held, 'CIE', $receipts, $working && $interval && $this->meets($receipts, 4, 3, 3));
        }
        if ($discipline !== 'conformation' && $registered) {
            $this->sportTitles($discipline, $history, $held, $awards);
        }
        if ($discipline === 'agility' && ($profile['registered'] ?? false) && ($profile['recognized_fci'] ?? false) && ($profile['pedigree_complete'] ?? false)) {
            $receipts = $this->withCode($history, 'CACIAG');
            foreach ($this->withCode($history, 'R_CACIAG') as $reserve) {
                if (in_array('CIAG', $reserve['details']['winner_championships'] ?? [], true)) {
                    $receipts[] = $reserve;
                }
            }
            $showGrade = array_filter($credentials, fn (array $credential): bool => ($credential['code'] ?? '') === 'SHOW_GRADE'
                && ($credential['discipline'] ?? '') === 'conformation' && ($credential['rank'] ?? '') === 'cacib'
                && ($credential['awarded_at'] ?? 0) <= $at
                && in_array($credential['details']['grade'] ?? '', ['good', 'very_good', 'excellent'], true));
            $this->grant($awards, $held, 'CIAG', $receipts, $showGrade !== [] && $this->meets($receipts, 2, 2, 2)
                && $this->span($receipts) >= $this->balance['regulations']['championship_interval_months'] * PetAgeRules::SECONDS_PER_MONTH);
        }

        return $awards;
    }

    /**
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $credentials
     * @return array<string,mixed>
     */
    public function progression(string $discipline, array $profile, array $credentials): array
    {
        $level = max(1, min(3, (int) ($profile['agility_level'] ?? 1)));
        $available = [$level];
        if ($discipline === 'agility') {
            foreach ($credentials as $credential) {
                if (in_array($credential['code'] ?? '', ['AGILITY_START', 'AGILITY_QUALIFICATION'], true)) {
                    $level = max($level, (int) ($credential['details']['level'] ?? 1));
                }
            }
            $available = [$level];
            if ($level < 3) {
                $receipts = array_values(array_filter($credentials, fn (array $credential): bool => ($credential['code'] ?? '') === 'AGILITY_QUALIFICATION'
                    && ($credential['details']['level'] ?? 0) === $level && ($credential['details']['format'] ?? '') === 'agility'
                    && ($credential['details']['faults'] ?? PHP_INT_MAX) <= 5.99));
                $clear = array_values(array_filter($receipts, fn (array $credential): bool => (float) $credential['details']['faults'] === 0.0));
                if ($this->meets($clear, 3, 2)) {
                    $level++;
                    $available = [$level];
                } elseif ($clear !== [] && $this->meets($receipts, 3, 2)) {
                    $available[] = $level + 1;
                }
            }
        }
        $levels = $profile['nosework_levels'] ?? ['pr1' => 0, 'pr2' => 0];
        $tests = $profile['nosework_tests'] ?? ['pr1' => [], 'pr2' => []];
        $nw0 = $profile['nosework_nw0_qualifications'] ?? ['pr1' => 0, 'pr2' => 0];
        foreach (['pr1', 'pr2'] as $program) {
            $tests[$program] ??= [];
            $levels[$program] ??= 0;
            $nw0[$program] ??= 0;
            foreach ($credentials as $credential) {
                if (($credential['details']['program'] ?? '') !== $program) {
                    continue;
                }
                if (($credential['code'] ?? '') === 'NW_ODOR_TEST' && in_array($credential['details']['odor'] ?? '', $this->balance['regulations']['nosework']['odors'][$program], true)) {
                    $tests[$program][] = $credential['details']['odor'];
                }
                if (in_array($credential['code'] ?? '', ['NW_START', 'NW_CLASS_QUALIFICATION'], true)) {
                    $started = $credential['details']['level'] ?? 'nw_0';
                    $levels[$program] = max($levels[$program], $started === 'nw_v' ? 1 : (int) substr($started, -1));
                }
            }
            $tests[$program] = array_values(array_unique($tests[$program]));
            $qualifications = array_values(array_filter($credentials, fn (array $credential): bool => ($credential['code'] ?? '') === 'NW_CLASS_QUALIFICATION'
                && ($credential['details']['program'] ?? '') === $program
                && ($credential['details']['qualified_categories'] ?? 0) >= $this->balance['regulations']['nosework']['qualification_categories']));
            $nw0Receipts = array_filter($qualifications, fn (array $credential): bool => ($credential['details']['level'] ?? '') === 'nw_0');
            $nw0[$program] = max($nw0[$program], count(array_unique(array_column($nw0Receipts, 'entry_id'))));
            for ($current = $levels[$program]; $current < 3; $current++) {
                $receipts = array_filter($qualifications, fn (array $credential): bool => ($credential['details']['level'] ?? '') === 'nw_'.$current);
                $nextClass = 'nw_'.($current + 1);
                $requiredOdors = array_slice($this->balance['regulations']['nosework']['odors'][$program], 0, $this->balance['regulations']['nosework']['required_odors'][$nextClass]);
                if (count(array_unique(array_column($receipts, 'entry_id'))) < $this->balance['regulations']['nosework']['advancement_events']
                    || count(array_intersect($requiredOdors, $tests[$program])) !== count($requiredOdors)) {
                    break;
                }
                $levels[$program] = $current + 1;
            }
        }

        return ['agility_level' => $level, 'agility_available_levels' => $available, 'agility_highest_started' => $level,
            'nosework_levels' => $levels, 'nosework_tests' => $tests, 'nosework_nw0_qualifications' => $nw0];
    }

    /**
     * @param  list<array<string,mixed>>  $history
     * @param  list<string>  $held
     * @param  list<array<string,mixed>>  $awards
     */
    private function sportTitles(string $discipline, array $history, array $held, array &$awards): void
    {
        $code = match ($discipline) {
            'agility' => 'CACAg', 'nosework' => 'CACNW', 'canicross' => 'CACT', default => '',
        };
        if ($code === '') {
            return;
        }
        $ordinary = $this->withCode($history, $code);
        $cups = $this->withCode($history, 'SPORT_CUP_RUS');
        $rkf = $this->withCode($history, 'SPORT_CH_RKF');
        $russia = $this->withCode($history, 'SPORT_CH_RUS');
        $regional = $this->withCode($history, 'SPORT_REGIONAL_CH');
        $eligible = $this->meets([...$cups, ...$rkf], 2, 2) && $cups !== [] && $rkf !== [];
        $eligible = $eligible || $this->meets($cups, 2, 2) || $this->meets($rkf, 2, 2);
        $eligible = $eligible || ($cups !== [] && $this->meets($ordinary, 2, 2) && $this->meets([...$cups, ...$ordinary], 3, 3));
        $eligible = $eligible || ($rkf !== [] && $this->meets($ordinary, 2, 2) && $this->meets([...$rkf, ...$ordinary], 3, 3));
        $eligible = $eligible || ($this->meets($ordinary, 5, 4) && $this->distinct($ordinary, 'region') >= 3);
        $eligible = $eligible || (count($regional) >= 2 && $this->distinct($regional, 'region') >= 2);
        $eligible = $eligible || ($regional !== [] && [...$russia, ...$rkf, ...$cups] !== []);
        $this->grant($awards, $held, 'SPORT_NATIONAL_CH', [...$ordinary, ...$cups, ...$rkf, ...$russia, ...$regional], $eligible);
        $breed = $this->withCode($history, $code.'_BREED');
        $this->grant($awards, $held, 'SPORT_BREED_CH', $breed, $this->meets($breed, 3, 2));
        $regionalCertificates = $this->withCode($history, 'REG_'.$code);
        $regions = [];
        foreach ($regionalCertificates as $receipt) {
            $regions[$receipt['region'] ?? ''][] = $receipt;
        }
        foreach ($regions as $region => $receipts) {
            if ($region !== '' && $this->meets($receipts, 3, 2)) {
                $this->grant($awards, $held, 'SPORT_REGIONAL_CH', $receipts, true);
                break;
            }
        }
    }

    /**
     * @param  list<array<string,mixed>>  $history
     * @return list<array<string,mixed>>
     */
    private function credits(array $history, string $code, string $champion): array
    {
        $credits = $this->withCode($history, $code);
        $unconverted = [];
        foreach ($this->withCode($history, 'R_'.$code) as $reserve) {
            if (in_array($champion, $reserve['details']['winner_championships'] ?? [], true)) {
                $credits[] = $reserve;
            } else {
                $unconverted[] = $reserve;
            }
        }
        if (count($unconverted) >= 2) {
            $credits[] = $unconverted[1];
        }

        return $credits;
    }

    /**
     * @param  list<array<string,mixed>>  $history
     * @return list<array<string,mixed>>
     */
    private function withCode(array $history, string $code): array
    {
        return array_values(array_filter($history, fn (array $credential): bool => ($credential['code'] ?? '') === $code));
    }

    /** @param list<array<string,mixed>> $receipts */
    private function meets(array $receipts, int $count, int $judges, int $countries = 0): bool
    {
        return count(array_unique(array_column($receipts, 'entry_id'))) >= $count
            && $this->distinct($receipts, 'judgeId') >= $judges && $this->distinct($receipts, 'country') >= $countries;
    }

    /** @param list<array<string,mixed>> $receipts */
    private function distinct(array $receipts, string $key): int
    {
        return count(array_unique(array_filter(array_column($receipts, $key), fn (mixed $value): bool => is_string($value) && $value !== '')));
    }

    /** @param list<array<string,mixed>> $receipts */
    private function span(array $receipts): int
    {
        $timestamps = array_column($receipts, 'awarded_at');

        return $timestamps === [] ? 0 : max($timestamps) - min($timestamps);
    }

    /**
     * @param  list<array<string,mixed>>  $awards
     * @param  list<string>  $held
     * @param  list<array<string,mixed>>  $receipts
     */
    private function grant(array &$awards, array $held, string $code, array $receipts, bool $eligible): void
    {
        if (! $eligible || $receipts === [] || in_array($code, $held, true)) {
            return;
        }
        $last = $receipts[array_key_last($receipts)];
        $awards[] = ['entry_id' => $last['entry_id'], 'code' => $code, 'kind' => 'championship',
            'details' => ['credential_entry_ids' => array_values(array_unique(array_column($receipts, 'entry_id')))]];
    }
}
