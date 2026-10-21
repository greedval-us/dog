<?php

namespace App\Modules\Pets\Calculators;

final class EventChampionshipRules
{
    private const array NATIONAL_YOUTH_TITLE_RULES = ['JCH_RUS' => ['JCAC', 3], 'VCH_RUS' => ['VCAC', 3]];

    private const array RKF_TITLE_CERTIFICATES = ['CH_RKF' => 'CH_RKF_CERT', 'JCH_RKF' => 'JCH_RKF_CERT', 'VCH_RKF' => 'VCH_RKF_CERT'];

    private const array CLUB_TITLE_RULES = ['CH_CLUB' => ['KCHK', 4, 2], 'JCH_CLUB' => ['JKCHK', 3, 1], 'VCH_CLUB' => ['VKCHK', 3, 1]];

    private const array RUSSIAN_GRAND_TITLE_COMPONENTS = ['GRAND_CH_RUS' => ['CH_RUS', 'CH_CLUB'], 'JGRAND_CH_RUS' => ['JCH_RUS', 'JCH_CLUB'], 'VGRAND_CH_RUS' => ['VCH_RUS', 'VCH_CLUB']];

    private const array INTERNATIONAL_YOUTH_CERTIFICATES = ['CIB_J' => 'CACIB_J', 'CIB_V' => 'CACIB_V'];

    private const int RUSSIAN_STANDARD_CERTIFICATES = 4;

    private const int RUSSIAN_NO_INTERVAL_CERTIFICATES = 6;

    private const array RUSSIAN_SPECIAL_CERTIFICATE_RANKS = ['cacib', 'cac_rkf_special'];

    private const array INTERNATIONAL_EXHIBITION_REQUIREMENTS = [4, 3, 3];

    private const array INTERNATIONAL_WORKING_REQUIREMENTS = [2, 2, 2];

    private const array INTERNATIONAL_YOUTH_REQUIREMENTS = [3, 3, 3];

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
            foreach (self::NATIONAL_YOUTH_TITLE_RULES as $title => [$certificate, $count]) {
                $credits = $this->credits($national, $certificate, $title);
                $this->grant($awards, $held, $title, $credits, $this->meets($credits, $count, $count));
            }
            $cac = $this->credits($national, 'CAC', 'CH_RUS');
            $year = $this->balance['regulations']['championship_interval_months'] * PetAgeRules::SECONDS_PER_MONTH;
            $allSpecial = array_values(array_filter($cac, fn (array $credential): bool => in_array($credential['rank'] ?? '', self::RUSSIAN_SPECIAL_CERTIFICATE_RANKS, true)));
            $adultEligible = $this->meets($cac, self::RUSSIAN_STANDARD_CERTIFICATES, self::RUSSIAN_STANDARD_CERTIFICATES)
                && (in_array('JCH_RUS', [...$held, ...array_column($awards, 'code')], true) || $this->span($cac) >= $year || $this->meets($allSpecial, self::RUSSIAN_STANDARD_CERTIFICATES, self::RUSSIAN_STANDARD_CERTIFICATES) || $this->meets($cac, self::RUSSIAN_NO_INTERVAL_CERTIFICATES, self::RUSSIAN_NO_INTERVAL_CERTIFICATES));
            $this->grant($awards, $held, 'CH_RUS', $cac, $adultEligible);
            foreach (self::RKF_TITLE_CERTIFICATES as $title => $certificate) {
                $receipts = $this->withCode($national, $certificate);
                $this->grant($awards, $held, $title, $receipts, $receipts !== []);
            }
            foreach (self::CLUB_TITLE_RULES as $title => [$certificate, $count, $monobreed]) {
                $receipts = $this->withCode($national, $certificate);
                $specialties = array_filter($receipts, fn (array $credential): bool => ($credential['rank'] ?? '') === 'specialty');
                $working = $title !== 'CH_CLUB' || ! ($profile['working_breed'] ?? false) || ($profile['working_certificate'] ?? false);
                $this->grant($awards, $held, $title, $receipts, $working && $this->meets($receipts, $count, $count) && count($specialties) >= $monobreed);
            }
            $available = [...$held, ...array_column($awards, 'code')];
            foreach (self::RUSSIAN_GRAND_TITLE_COMPONENTS as $title => $required) {
                $this->grant($awards, $held, $title, $national, count(array_intersect($required, $available)) === count($required));
            }
        }
        if ($discipline === 'conformation' && ($profile['recognized_fci'] ?? false) && ($profile['registered'] ?? false) && ($profile['pedigree_complete'] ?? false)) {
            foreach (self::INTERNATIONAL_YOUTH_CERTIFICATES as $title => $certificate) {
                $receipts = $this->withCode($history, $certificate);
                $this->grant($awards, $held, $title, $receipts, $this->meets($receipts, ...self::INTERNATIONAL_YOUTH_REQUIREMENTS));
            }
            $receipts = $this->internationalCredits($history, 'CACIB', 'CIB');
            $working = $profile['working_breed'] ?? false;
            $interval = $this->span($receipts) >= $this->balance['regulations']['championship_interval_months'] * PetAgeRules::SECONDS_PER_MONTH;
            $this->grant($awards, $held, 'CIB', $receipts, $interval && ($working
                ? (($profile['working_certificate'] ?? false) && $this->meets($receipts, ...self::INTERNATIONAL_WORKING_REQUIREMENTS))
                : $this->meets($receipts, ...self::INTERNATIONAL_EXHIBITION_REQUIREMENTS)));
            $this->grant($awards, $held, 'CIE', $receipts, $working && $interval && $this->meets($receipts, ...self::INTERNATIONAL_EXHIBITION_REQUIREMENTS));
        }
        if ($discipline !== 'conformation' && $registered) {
            $this->sportTitles($discipline, $history, $held, $awards);
        }
        if ($discipline === 'agility' && ($profile['registered'] ?? false) && ($profile['recognized_fci'] ?? false) && ($profile['pedigree_complete'] ?? false)) {
            $receipts = $this->internationalCredits($history, 'CACIAG', 'CIAG');
            $showGrade = $this->internationalShowGrades($credentials, $at);
            $this->grant($awards, $held, 'CIAG', $receipts, $showGrade !== [] && $this->meets($receipts, 2, 2, 2)
                && $this->span($receipts) >= $this->balance['regulations']['championship_interval_months'] * PetAgeRules::SECONDS_PER_MONTH);
        }

        return $awards;
    }

    /**
     * Progress uses the same credit conversion, thresholds and final award verdict as settlement.
     *
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $credentials
     * @return list<array<string,mixed>>
     */
    public function progress(string $discipline, array $profile, array $credentials, int $at): array
    {
        if (! in_array($discipline, ['conformation', 'agility'], true)) {
            return [];
        }
        $history = array_values(array_filter($credentials, fn (array $receipt): bool => ($receipt['discipline'] ?? '') === $discipline
            && ($receipt['awarded_at'] ?? PHP_INT_MAX) <= $at && ($receipt['title_id'] ?? 0) > 0));
        $validAfter = $at - $this->balance['regulations']['certificate_validity_months'] * PetAgeRules::SECONDS_PER_MONTH;
        $national = array_values(array_filter($history, fn (array $receipt): bool => $receipt['awarded_at'] >= $validAfter));
        $awards = $this->calculate($discipline, $profile, $credentials, $at);
        $nationalGates = $this->progressProfileRequirements($profile, 'rkf');
        $internationalGates = $this->progressProfileRequirements($profile, 'fci');
        $goals = [];
        $year = $this->balance['regulations']['championship_interval_months'] * PetAgeRules::SECONDS_PER_MONTH;
        if ($discipline === 'conformation') {
            foreach (self::NATIONAL_YOUTH_TITLE_RULES as $code => [$certificate, $count]) {
                $receipts = $this->credits($national, $certificate, $code);
                $sources = $this->progressCreditSources($national, $receipts, $certificate, $code);
                $requirements = [...$nationalGates, ...$this->progressReceiptRequirements($receipts, [$certificate], $count, $count)];
                $goals[] = $this->progressGoal($code, [$this->progressPath('standard', $sources, $requirements,
                    [$this->progressTarget($receipts, [$certificate], $count)],
                    $this->progressExcluded($history, $sources, [$certificate, 'R_'.$certificate], $validAfter))], $profile, $awards);
            }
            $receipts = $this->credits($national, 'CAC', 'CH_RUS');
            $sources = $this->progressCreditSources($national, $receipts, 'CAC', 'CH_RUS');
            $special = array_values(array_filter($receipts, fn (array $receipt): bool => in_array($receipt['rank'] ?? '', self::RUSSIAN_SPECIAL_CERTIFICATE_RANKS, true)));
            $specialSources = $this->progressCreditSources($national, $special, 'CAC', 'CH_RUS');
            $counts = $this->progressReceiptRequirements($receipts, ['CAC'], self::RUSSIAN_STANDARD_CERTIFICATES, self::RUSSIAN_STANDARD_CERTIFICATES);
            $target = $this->progressTarget($receipts, ['CAC'], self::RUSSIAN_STANDARD_CERTIFICATES);
            $interval = $this->progressInterval($receipts, $year);
            $availableTitles = [...($profile['championships'] ?? []), ...array_column($awards, 'code')];
            $juniorProof = [];
            if (! in_array('JCH_RUS', $profile['championships'] ?? [], true) && in_array('JCH_RUS', array_column($awards, 'code'), true)) {
                $juniorProof = $this->progressCreditSources($national, $this->credits($national, 'JCAC', 'JCH_RUS'), 'JCAC', 'JCH_RUS');
            }
            $juniorTargets = $this->meets($receipts, self::RUSSIAN_STANDARD_CERTIFICATES, self::RUSSIAN_STANDARD_CERTIFICATES) ? [] : [$target];
            if (! in_array('JCH_RUS', $availableTitles, true)) {
                $juniorTargets[] = ['codes' => ['JCH_RUS']];
            }
            $paths = [
                $this->progressPath('standard_interval', $sources, [...$nationalGates, ...$counts, $interval],
                    [[...$target, 'notBefore' => $interval['ready_at'] ?? $at]]),
                $this->progressPath('junior_champion', [...$sources, ...$juniorProof], [...$nationalGates, ...$counts,
                    $this->progressRequirement('titles', 'document', (int) in_array('JCH_RUS', $availableTitles, true), 1, ['JCH_RUS'])], $juniorTargets),
                $this->progressPath('special_certificates', $specialSources, [...$nationalGates,
                    ...$this->progressReceiptRequirements($special, ['CAC'], self::RUSSIAN_STANDARD_CERTIFICATES, self::RUSSIAN_STANDARD_CERTIFICATES)],
                    array_map(fn (string $rank): array => [...$this->progressTarget($special, ['CAC'], self::RUSSIAN_STANDARD_CERTIFICATES), 'rank' => $rank], self::RUSSIAN_SPECIAL_CERTIFICATE_RANKS)),
                $this->progressPath('six_certificates', $sources, [...$nationalGates,
                    ...$this->progressReceiptRequirements($receipts, ['CAC'], self::RUSSIAN_NO_INTERVAL_CERTIFICATES, self::RUSSIAN_NO_INTERVAL_CERTIFICATES)], [$this->progressTarget($receipts, ['CAC'], self::RUSSIAN_NO_INTERVAL_CERTIFICATES)]),
            ];
            $goals[] = $this->progressGoal('CH_RUS', $paths, $profile, $awards);
            foreach (self::RKF_TITLE_CERTIFICATES as $code => $certificate) {
                $receipts = $this->withCode($national, $certificate);
                $goals[] = $this->progressGoal($code, [$this->progressPath('standard', $receipts,
                    [...$nationalGates, ...$this->progressReceiptRequirements($receipts, [$certificate], 1)],
                    [['codes' => [$certificate]]], $this->progressExcluded($history, $receipts, [$certificate], $validAfter))], $profile, $awards);
            }
            foreach (self::CLUB_TITLE_RULES as $code => [$certificate, $count, $monobreed]) {
                $receipts = $this->withCode($national, $certificate);
                $specialties = array_values(array_filter($receipts, fn (array $receipt): bool => ($receipt['rank'] ?? '') === 'specialty'));
                $working = $code !== 'CH_CLUB' || ! ($profile['working_breed'] ?? false) || ($profile['working_certificate'] ?? false);
                $target = $this->progressTarget($receipts, [$certificate], $count);
                if (count($specialties) < $monobreed) {
                    $target['rank'] = 'specialty';
                }
                $goals[] = $this->progressGoal($code, [$this->progressPath('standard', $receipts,
                    [...$nationalGates, ...$this->progressReceiptRequirements($receipts, [$certificate], $count, $count),
                        $this->progressRequirement('specialty_certificates', 'count', count($specialties), $monobreed, [$certificate]),
                        $this->progressRequirement('working_certificate', 'document', (int) $working, 1)],
                    [$target],
                    $this->progressExcluded($history, $receipts, [$certificate], $validAfter))], $profile, $awards);
            }
            foreach (self::RUSSIAN_GRAND_TITLE_COMPONENTS as $code => $required) {
                $receipts = array_values(array_filter($history, fn (array $receipt): bool => in_array($receipt['code'], $required, true)));
                $requirements = [...$nationalGates];
                foreach ($required as $component) {
                    $requirements[] = $this->progressRequirement('titles', 'document', (int) in_array($component, $availableTitles, true), 1, [$component]);
                }
                $missing = array_values(array_diff($required, $availableTitles));
                $goals[] = $this->progressGoal($code, [$this->progressPath('national_and_breed_championships', $receipts, $requirements, $missing === [] ? [] : [['codes' => $missing]])], $profile, $awards);
            }
            foreach (self::INTERNATIONAL_YOUTH_CERTIFICATES as $code => $certificate) {
                $receipts = $this->withCode($history, $certificate);
                $goals[] = $this->progressGoal($code, [$this->progressPath('standard', $receipts,
                    [...$internationalGates, ...$this->progressReceiptRequirements($receipts, [$certificate], ...self::INTERNATIONAL_YOUTH_REQUIREMENTS)],
                    [$this->progressTarget($receipts, [$certificate], self::INTERNATIONAL_YOUTH_REQUIREMENTS[1], self::INTERNATIONAL_YOUTH_REQUIREMENTS[2])])], $profile, $awards);
            }
            $receipts = $this->internationalCredits($history, 'CACIB', 'CIB');
            foreach (['CIB', 'CIE'] as $code) {
                $working = (bool) ($profile['working_breed'] ?? false);
                $requirements = [...$internationalGates,
                    ...$this->progressReceiptRequirements($receipts, ['CACIB'], ...($working && $code === 'CIB' ? self::INTERNATIONAL_WORKING_REQUIREMENTS : self::INTERNATIONAL_EXHIBITION_REQUIREMENTS)),
                    $this->progressInterval($receipts, $year)];
                if ($code === 'CIE') {
                    $requirements[] = $this->progressRequirement('working_breed', 'document', (int) $working, 1);
                } elseif ($working) {
                    $requirements[] = $this->progressRequirement('working_certificate', 'document', (int) ($profile['working_certificate'] ?? false), 1);
                }
                $goals[] = $this->progressGoal($code, [$this->progressPath('standard', $receipts, $requirements,
                    [[...$this->progressTarget($receipts, ['CACIB'], ($working && $code === 'CIB' ? self::INTERNATIONAL_WORKING_REQUIREMENTS : self::INTERNATIONAL_EXHIBITION_REQUIREMENTS)[1], ($working && $code === 'CIB' ? self::INTERNATIONAL_WORKING_REQUIREMENTS : self::INTERNATIONAL_EXHIBITION_REQUIREMENTS)[2]),
                        'notBefore' => $this->progressInterval($receipts, $year)['ready_at'] ?? $at]])], $profile, $awards);
            }
        } else {
            $receipts = $this->internationalCredits($history, 'CACIAG', 'CIAG');
            $showGrade = array_values(array_filter($this->internationalShowGrades($credentials, $at), fn (array $receipt): bool => ($receipt['title_id'] ?? 0) > 0));
            $targets = [[...$this->progressTarget($receipts, ['CACIAG'], self::INTERNATIONAL_WORKING_REQUIREMENTS[1], self::INTERNATIONAL_WORKING_REQUIREMENTS[2]),
                'notBefore' => $this->progressInterval($receipts, $year)['ready_at'] ?? $at]];
            if ($showGrade === []) {
                $targets[] = ['codes' => ['SHOW_GRADE']];
            }
            $goals[] = $this->progressGoal('CIAG', [$this->progressPath('standard', [...$receipts, ...$showGrade],
                [...$internationalGates, ...$this->progressReceiptRequirements($receipts, ['CACIAG'], ...self::INTERNATIONAL_WORKING_REQUIREMENTS),
                    $this->progressInterval($receipts, $year), $this->progressRequirement('show_grade', 'document', (int) ($showGrade !== []), 1, ['SHOW_GRADE'])],
                $targets)], $profile, $awards);
        }

        return $goals;
    }

    /** @param list<string> $codes
     * @return array<string,mixed>
     */
    public function progressRequirement(string $key, string $kind, int $current, int $required, array $codes = []): array
    {
        return ['key' => $key, 'kind' => $kind, 'current' => $current, 'required' => $required, 'met' => $current >= $required, 'codes' => $codes];
    }

    /** @param list<array<string,mixed>> $receipts
     * @param  list<string>  $codes
     * @return list<array<string,mixed>>
     */
    public function progressReceiptRequirements(array $receipts, array $codes, int $count, int $judges = 0, int $countries = 0): array
    {
        $requirements = [$this->progressRequirement('certificates', 'count', count(array_unique(array_column($receipts, 'entry_id'))), $count, $codes)];
        foreach (['judges' => ['judgeId', $judges], 'countries' => ['country', $countries]] as $key => [$field, $required]) {
            if ($required > 0) {
                $requirements[] = $this->progressRequirement($key, 'distinct', $this->distinct($receipts, $field), $required);
            }
        }

        return $requirements;
    }

    /** @param array<string,mixed> $profile
     * @return list<array<string,mixed>>
     */
    public function progressProfileRequirements(array $profile, string $system, bool $fullPedigree = true): array
    {
        $requirements = [$this->progressRequirement('registered', 'document', (int) ($profile['registered'] ?? false), 1),
            $this->progressRequirement('recognized_'.$system, 'document', (int) ($profile['recognized_'.$system] ?? false), 1)];
        if ($fullPedigree) {
            $requirements[] = $this->progressRequirement('pedigree_complete', 'document', (int) ($profile['pedigree_complete'] ?? false), 1);
        }

        return $requirements;
    }

    /** @param list<array<string,mixed>> $receipts
     * @param  list<array<string,mixed>>  $requirements
     * @param  list<array<string,mixed>>  $targets
     * @param  list<array<string,mixed>>  $excluded
     * @return array<string,mixed>
     */
    public function progressPath(string $key, array $receipts, array $requirements, array $targets, array $excluded = []): array
    {
        $ids = array_values(array_unique(array_map(fn (array $receipt): int => (int) $receipt['title_id'], array_filter($receipts, fn (array $receipt): bool => ($receipt['title_id'] ?? 0) > 0))));
        $ready = ! in_array(false, array_column($requirements, 'met'), true);

        return ['key' => $key, 'ready' => $ready, 'requirements' => $requirements,
            'proof_title_ids' => $ready ? $ids : [], 'available_title_ids' => $ids, 'excluded' => $excluded, 'targets' => $targets];
    }

    /** @param list<array<string,mixed>> $paths
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $awards
     * @return array<string,mixed>
     */
    public function progressGoal(string $code, array $paths, array $profile, array $awards, string $scope = '', bool $repeatable = false, ?bool $achieved = null): array
    {
        $achieved ??= ! $repeatable && in_array($code, $profile['championships'] ?? [], true);
        $matching = array_values(array_filter($awards, fn (array $award): bool => $award['code'] === $code
            && ($scope === '' || ($award['scope_key'] ?? '') === $scope)));
        foreach ($paths as &$path) {
            if ($achieved || $matching === []) {
                if ($path['ready'] && ! $achieved) {
                    $path['requirements'][] = $this->progressRequirement('compatible_evidence', 'compatible', 0, 1);
                }
                $path['ready'] = false;
                $path['proof_title_ids'] = [];
            } elseif ($path['ready']) {
                foreach ($matching as $award) {
                    if (($award['details']['exchange_path'] ?? 'standard') === $path['key']) {
                        $path['proof_title_ids'] = $award['details']['credential_title_ids'] ?? $path['proof_title_ids'];
                        break;
                    }
                }
            }
        }
        unset($path);

        return ['code' => $code, 'scope_key' => $scope, 'repeatable' => $repeatable, 'achieved' => $achieved, 'paths' => $paths];
    }

    /** @param list<array<string,mixed>> $receipts
     * @return array<string,mixed>
     */
    private function progressInterval(array $receipts, int $required): array
    {
        $requirement = $this->progressRequirement('interval', 'interval', $this->span($receipts), $required);
        $timestamps = array_column($receipts, 'awarded_at');
        if ($timestamps !== []) {
            $requirement['ready_at'] = min($timestamps) + $required;
        }

        return $requirement;
    }

    /** @param list<array<string,mixed>> $receipts
     * @return list<string>
     */
    public function progressValues(array $receipts, string $key): array
    {
        return array_values(array_unique(array_filter(array_column($receipts, $key), fn (mixed $value): bool => is_string($value) && $value !== '')));
    }

    /** @param list<array<string,mixed>> $receipts
     * @param  list<string>  $codes
     * @return array<string,mixed>
     */
    public function progressTarget(array $receipts, array $codes, int $judges = 0, int $countries = 0): array
    {
        return ['codes' => $codes,
            'excludeJudges' => $this->distinct($receipts, 'judgeId') < $judges ? $this->progressValues($receipts, 'judgeId') : [],
            'excludeCountries' => $this->distinct($receipts, 'country') < $countries ? $this->progressValues($receipts, 'country') : []];
    }

    /**
     * @param  list<array<string,mixed>>  $history
     * @param  list<array<string,mixed>>  $credits
     * @return list<array<string,mixed>>
     */
    private function progressCreditSources(array $history, array $credits, string $code, string $champion): array
    {
        $unconverted = array_values(array_filter($this->withCode($history, 'R_'.$code),
            fn (array $receipt): bool => ! in_array($champion, $receipt['details']['winner_championships'] ?? [], true)));
        if (count($unconverted) >= 2 && in_array($unconverted[1]['title_id'], array_column($credits, 'title_id'), true)) {
            $credits[] = $unconverted[0];
        }

        return $credits;
    }

    /** @param list<array<string,mixed>> $history
     * @return list<array<string,mixed>>
     */
    private function internationalCredits(array $history, string $certificate, string $champion): array
    {
        $receipts = $this->withCode($history, $certificate);
        foreach ($this->withCode($history, 'R_'.$certificate) as $reserve) {
            if (in_array($champion, $reserve['details']['winner_championships'] ?? [], true)) {
                $receipts[] = $reserve;
            }
        }

        return $receipts;
    }

    /** @param list<array<string,mixed>> $credentials
     * @return list<array<string,mixed>>
     */
    private function internationalShowGrades(array $credentials, int $at): array
    {
        return array_values(array_filter($credentials, fn (array $receipt): bool => ($receipt['code'] ?? '') === 'SHOW_GRADE'
            && ($receipt['discipline'] ?? '') === 'conformation' && ($receipt['rank'] ?? '') === 'cacib'
            && ($receipt['awarded_at'] ?? 0) <= $at
            && in_array($receipt['details']['grade'] ?? '', ['good', 'very_good', 'excellent'], true)));
    }

    /** @param list<array<string,mixed>> $history
     * @param  list<array<string,mixed>>  $accepted
     * @param  list<string>  $codes
     * @param  list<int>  $usedIds
     * @return list<array<string,mixed>>
     */
    public function progressExcluded(array $history, array $accepted, array $codes, int $validAfter = PHP_INT_MIN, array $usedIds = []): array
    {
        $ids = array_column($accepted, 'title_id');
        $excluded = [];
        foreach ($history as $receipt) {
            $id = (int) ($receipt['title_id'] ?? 0);
            if ($id <= 0 || ! in_array($receipt['code'], $codes, true) || in_array($id, $ids, true)) {
                continue;
            }
            $excluded[] = ['title_id' => $id, 'reason' => in_array($id, $usedIds, true) ? 'used' : (($receipt['awarded_at'] ?? 0) < $validAfter ? 'expired' : 'ineligible')];
        }

        return $excluded;
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
