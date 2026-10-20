<?php

namespace App\Modules\Pets\Calculators;

/** @phpstan-type ExchangeRule array{counts:array<string,int>,judges:int,regions:int,distinct_events?:bool} */
final class RegionalSportChampionships
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
        $certificate = $this->balance['certificate_codes'][$discipline] ?? null;
        if (! is_string($certificate) || ! $this->eligibleProfile($profile)) {
            return [];
        }
        $history = $this->history($discipline, $credentials, $at);
        $held = array_column(array_filter($history, fn (array $credential): bool => ($credential['kind'] ?? '') === 'championship'), 'code');
        $available = $this->available($history);
        $awards = [];
        $regions = [];
        foreach ($this->withCodes($available, ['REG_'.$certificate], 'certificate') as $credential) {
            if ((int) ($credential['regulation_version'] ?? 1) === 2 && $credential['region'] !== '') {
                $regions[$credential['region']][] = $credential;
            }
        }
        foreach ($regions as $region => $regional) {
            while (($proof = $this->selectForRule($regional, $this->regionalRule($certificate))) !== []) {
                $ids = array_column($proof, 'title_id');
                $sortedIds = $ids;
                sort($sortedIds, SORT_NUMERIC);
                $scope = 'region:'.$region.':issue:'.hash('sha256', implode(',', $sortedIds));
                $awards[] = $this->award('SPORT_REGIONAL_CH', $proof, 'regional_3_certificates', $scope, ['region' => $region]);
                $regional = array_values(array_filter($regional, fn (array $credential): bool => ! in_array($credential['title_id'], $ids, true)));
            }
        }
        if (! in_array('SPORT_NATIONAL_CH', $held, true)) {
            foreach ($this->nationalPaths($certificate) as $path => $rule) {
                $proof = $this->selectForRule($available, $rule);
                if ($proof !== []) {
                    $awards[] = $this->award('SPORT_NATIONAL_CH', $proof, 'national_'.$path);
                    break;
                }
            }
        }
        if (! in_array('SPORT_BREED_CH', $held, true)) {
            $proof = $this->selectForRule($available, $this->breedRule($certificate));
            if ($proof !== []) {
                $awards[] = $this->award('SPORT_BREED_CH', $proof, 'breed_3_certificates');
            }
        }

        return $awards;
    }

    /**
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $credentials
     * @return list<array<string,mixed>>
     */
    public function progress(string $discipline, array $profile, array $credentials, int $at): array
    {
        $certificate = $this->balance['certificate_codes'][$discipline] ?? null;
        if (! is_string($certificate)) {
            return [];
        }
        $sources = array_values(array_filter($credentials, fn (array $credential): bool => ($credential['discipline'] ?? '') === $discipline));
        $history = $this->history($discipline, $credentials, $at);
        $available = $this->available($history);
        $held = array_values(array_filter($history, fn (array $credential): bool => ($credential['kind'] ?? '') === 'championship'));
        $regions = [];
        $regionalAchievements = [];
        foreach ($history as $credential) {
            if (! in_array($credential['code'], ['REG_'.$certificate, 'SPORT_REGIONAL_CH'], true)) {
                continue;
            }
            $region = $this->region($credential);
            if ($region !== '') {
                $regions[$region] = true;
                if ($credential['code'] === 'SPORT_REGIONAL_CH' && ($credential['kind'] ?? '') === 'championship') {
                    $regionalAchievements[$region] = true;
                }
            }
        }
        $regions = array_keys($regions);
        sort($regions, SORT_STRING);
        $goals = [['code' => 'SPORT_REGIONAL_CH', 'scope_key' => 'region:any', 'repeatable' => true, 'achieved' => false,
            'paths' => [$this->progressPath($profile, $sources, $history, $available, $this->regionalRule($certificate), 'regional_3_certificates', $at, excludeRegions: $regions)]]];
        foreach ($regions as $region) {
            $goals[] = ['code' => 'SPORT_REGIONAL_CH', 'scope_key' => 'region:'.$region, 'repeatable' => true,
                'achieved' => isset($regionalAchievements[$region]),
                'paths' => [$this->progressPath($profile, $sources, $history, $available, $this->regionalRule($certificate), 'regional_3_certificates', $at, $region)]];
        }
        $nationalAchieved = in_array('SPORT_NATIONAL_CH', array_column($held, 'code'), true);
        $paths = [];
        if (! $nationalAchieved) {
            foreach ($this->nationalPaths($certificate) as $path => $rule) {
                $paths[] = $this->progressPath($profile, $sources, $history, $available, $rule, 'national_'.$path, $at);
            }
        }
        $goals[] = ['code' => 'SPORT_NATIONAL_CH', 'scope_key' => '', 'repeatable' => false, 'achieved' => $nationalAchieved, 'paths' => $paths];
        $breedAchieved = in_array('SPORT_BREED_CH', array_column($held, 'code'), true);
        $goals[] = ['code' => 'SPORT_BREED_CH', 'scope_key' => '', 'repeatable' => false, 'achieved' => $breedAchieved,
            'paths' => $breedAchieved ? [] : [$this->progressPath($profile, $sources, $history, $available, $this->breedRule($certificate), 'breed_3_certificates', $at)]];

        return $goals;
    }

    /**
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $sources
     * @param  list<array<string,mixed>>  $history
     * @param  list<array<string,mixed>>  $available
     * @param  ExchangeRule  $rule
     * @param  list<string>  $excludeRegions
     * @return array<string,mixed>
     */
    private function progressPath(array $profile, array $sources, array $history, array $available, array $rule, string $key, int $at, ?string $region = null, array $excludeRegions = []): array
    {
        $codes = $this->acceptedCodes(array_keys($rule['counts']));
        $matchesRegion = fn (array $credential): bool => ($region === null || $this->region($credential) === $region)
            && ! in_array($this->region($credential), $excludeRegions, true);
        $pool = array_values(array_filter($this->poolForRule($available, $rule), $matchesRegion));
        $proof = $this->selectForRule($pool, $rule);
        $requirements = $this->registryRequirements($profile);
        $deficient = [];
        foreach ($rule['counts'] as $code => $required) {
            $receipts = array_values(array_filter($pool, fn (array $credential): bool => $this->requiredCode($credential['code'], $rule['counts']) === $code));
            $current = count(array_unique(array_column($receipts, ($rule['distinct_events'] ?? true) ? 'entry_id' : 'title_id')));
            $requirements[] = $this->requirement('count:'.$code, 'count', $current, $required, $this->acceptedCodes([$code]));
            if ($current < $required) {
                $deficient = [...$deficient, ...$this->acceptedCodes([$code])];
            }
        }
        foreach (['judges' => 'judgeId', 'regions' => 'region'] as $requirement => $field) {
            if ($rule[$requirement] > 0) {
                $requirements[] = $this->requirement($requirement, 'distinct', $this->distinct($pool, $field), $rule[$requirement]);
            }
        }
        if ($proof === [] && count(array_filter($requirements, fn (array $requirement): bool => ! $requirement['met'])) === 0) {
            $requirements[] = $this->requirement('compatible_evidence', 'compatible', 0, 1);
        }
        $availableIds = array_map(fn (array $credential): int => (int) $credential['title_id'], $pool);
        $consumed = $this->consumedIds($history);
        $excluded = [];
        foreach ($sources as $source) {
            $id = (int) ($source['title_id'] ?? 0);
            if ($id <= 0 || ! in_array($source['code'] ?? '', $codes, true) || ! $matchesRegion($source) || in_array($id, $availableIds, true)) {
                continue;
            }
            $excluded[] = ['title_id' => $id, 'reason' => isset($consumed[$id]) && (int) ($source['awarded_at'] ?? PHP_INT_MAX) <= $at ? 'used' : 'ineligible'];
        }
        $ready = $proof !== [] && $this->eligibleProfile($profile);
        $targets = [];
        if (! $ready && $this->eligibleProfile($profile)) {
            $target = ['codes' => array_values(array_unique($deficient === [] ? $codes : $deficient))];
            if ($region !== null) {
                $target['region'] = $region;
            }
            $knownRegions = array_values(array_unique(array_filter(array_column($pool, 'region'), fn (mixed $value): bool => is_string($value) && $value !== '')));
            $regionExclusions = $excludeRegions;
            if ($rule['regions'] > $this->distinct($pool, 'region')) {
                $regionExclusions = [...$regionExclusions, ...$knownRegions];
            }
            if ($regionExclusions !== []) {
                $target['excludeRegions'] = array_values(array_unique($regionExclusions));
            }
            if ($rule['judges'] > $this->distinct($pool, 'judgeId')) {
                $judges = array_values(array_unique(array_filter(array_column($pool, 'judgeId'), fn (mixed $value): bool => is_string($value) && $value !== '')));
                if ($judges !== []) {
                    $target['excludeJudges'] = $judges;
                }
            }
            $targets[] = $target;
        }

        return ['key' => $key, 'ready' => $ready, 'requirements' => $requirements,
            'proof_title_ids' => array_map(fn (array $credential): int => (int) $credential['title_id'], $proof),
            'available_title_ids' => $availableIds, 'excluded' => $excluded, 'targets' => $targets];
    }

    /**
     * @param  list<array<string,mixed>>  $credentials
     * @return list<array<string,mixed>>
     */
    private function history(string $discipline, array $credentials, int $at): array
    {
        $history = array_values(array_filter($credentials, fn (array $credential): bool => ($credential['discipline'] ?? '') === $discipline
            && (int) ($credential['awarded_at'] ?? PHP_INT_MAX) <= $at));
        usort($history, fn (array $first, array $second): int => [$first['awarded_at'], $first['entry_id'], $first['title_id'] ?? 0] <=> [$second['awarded_at'], $second['entry_id'], $second['title_id'] ?? 0]);

        return $history;
    }

    /** @param list<array<string,mixed>> $history
     * @return list<array<string,mixed>>
     */
    private function available(array $history): array
    {
        $consumed = $this->consumedIds($history);
        $available = [];
        foreach ($history as $credential) {
            $id = (int) ($credential['title_id'] ?? 0);
            if ($id <= 0 || (int) ($credential['entry_id'] ?? 0) <= 0 || isset($consumed[$id])) {
                continue;
            }
            $credential['region'] = $this->region($credential);
            $available[] = $credential;
        }

        return $available;
    }

    /** @param array<string,mixed> $profile */
    private function eligibleProfile(array $profile): bool
    {
        return count(array_filter($this->registryRequirements($profile), fn (array $requirement): bool => ! $requirement['met'])) === 0;
    }

    /** @param array<string,mixed> $profile
     * @return list<array<string,mixed>>
     */
    private function registryRequirements(array $profile): array
    {
        return array_map(fn (string $key): array => $this->requirement($key, 'document', (int) ($profile[$key] ?? false), 1), ['registered', 'recognized_rkf']);
    }

    /** @param list<string> $codes
     * @return array<string,mixed>
     */
    private function requirement(string $key, string $kind, int $current, int $required, array $codes = []): array
    {
        $requirement = ['key' => $key, 'kind' => $kind, 'current' => min($current, $required), 'required' => $required, 'met' => $current >= $required];
        if ($codes !== []) {
            $requirement['codes'] = $codes;
        }

        return $requirement;
    }

    /** @return ExchangeRule */
    private function regionalRule(string $certificate): array
    {
        return ['counts' => ['REG_'.$certificate => 3], 'judges' => 2, 'regions' => 0];
    }

    /** @return ExchangeRule */
    private function breedRule(string $certificate): array
    {
        return ['counts' => [$certificate.'_BREED' => 3], 'judges' => 2, 'regions' => 0];
    }

    /** @param list<array<string,mixed>> $pool
     * @param  ExchangeRule  $rule
     * @return list<array<string,mixed>>
     */
    private function selectForRule(array $pool, array $rule): array
    {
        return $this->select($this->poolForRule($pool, $rule), $rule['counts'], $rule['judges'], $rule['regions'], distinctEvents: $rule['distinct_events'] ?? true);
    }

    /** @param list<array<string,mixed>> $pool
     * @param  ExchangeRule  $rule
     * @return list<array<string,mixed>>
     */
    private function poolForRule(array $pool, array $rule): array
    {
        return array_values(array_filter($pool, fn (array $credential): bool => isset($rule['counts'][$this->requiredCode($credential['code'], $rule['counts'])])
            && ($credential['kind'] ?? '') === (str_starts_with($credential['code'], 'SPORT_') ? 'championship' : 'certificate')
            && ($rule['judges'] === 0 || trim((string) ($credential['judgeId'] ?? '')) !== '')
            && (! str_starts_with($credential['code'], 'REG_') || ((int) ($credential['regulation_version'] ?? 1) === 2 && $credential['region'] !== ''))));
    }

    /** @param list<string> $codes
     * @return list<string>
     */
    private function acceptedCodes(array $codes): array
    {
        $accepted = [];
        foreach ($codes as $code) {
            $accepted = [...$accepted, ...($code === 'SPORT_NATIONAL_DIRECT_PROOF' ? ['SPORT_CH_RUS', 'SPORT_CH_RKF', 'SPORT_CUP_RUS'] : [$code])];
        }

        return array_values(array_unique($accepted));
    }

    /**
     * @param  list<array<string,mixed>>  $history
     * @return array<int,true>
     */
    private function consumedIds(array $history): array
    {
        $consumed = [];
        foreach ($history as $credential) {
            if (in_array('sport_exchange', $credential['details']['used_for'] ?? [], true)) {
                $consumed[(int) ($credential['title_id'] ?? 0)] = true;
            }
            if (($credential['kind'] ?? '') === 'championship' && (int) ($credential['regulation_version'] ?? 1) === 2
                && (($credential['details']['proof_purpose'] ?? '') === 'sport_exchange' || (int) ($credential['details']['policy_version'] ?? 1) === 2)) {
                foreach ($credential['details']['credential_title_ids'] ?? [] as $id) {
                    $consumed[(int) $id] = true;
                }
            }
        }

        return $consumed;
    }

    /** @return array<int,ExchangeRule> */
    private function nationalPaths(string $certificate): array
    {
        return [
            1 => ['counts' => ['SPORT_CUP_RUS' => 1, 'SPORT_CH_RKF' => 1], 'judges' => 2, 'regions' => 0],
            2 => ['counts' => ['SPORT_CUP_RUS' => 2], 'judges' => 2, 'regions' => 0],
            3 => ['counts' => ['SPORT_CUP_RUS' => 1, $certificate => 2], 'judges' => 3, 'regions' => 0],
            4 => ['counts' => ['SPORT_CH_RKF' => 2], 'judges' => 2, 'regions' => 0],
            5 => ['counts' => ['SPORT_CH_RKF' => 1, $certificate => 2], 'judges' => 3, 'regions' => 0],
            6 => ['counts' => [$certificate => 5], 'judges' => 4, 'regions' => 3],
            7 => ['counts' => ['SPORT_REGIONAL_CH' => 2], 'judges' => 0, 'regions' => 2, 'distinct_events' => false],
            8 => ['counts' => ['SPORT_REGIONAL_CH' => 1, 'SPORT_NATIONAL_DIRECT_PROOF' => 1], 'judges' => 0, 'regions' => 0, 'distinct_events' => false],
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $pool
     * @param  array<string,int>  $required
     * @param  list<array<string,mixed>>  $selected
     * @return list<array<string,mixed>>
     */
    private function select(array $pool, array $required, int $judges = 0, int $regions = 0, array $selected = [], int $offset = 0, bool $distinctEvents = true): array
    {
        $remaining = array_sum($required);
        if ($remaining === 0) {
            return $this->distinct($selected, 'judgeId') >= $judges && $this->distinct($selected, 'region') >= $regions ? $selected : [];
        }
        if ($this->distinct($selected, 'judgeId') + $remaining < $judges || $this->distinct($selected, 'region') + $remaining < $regions) {
            return [];
        }
        $events = $distinctEvents ? array_column($selected, 'entry_id') : [];
        $sources = array_column($selected, 'title_id');
        $candidates = [];
        $counts = [];
        foreach ($pool as $index => $credential) {
            $code = $this->requiredCode($credential['code'], $required);
            if ($index < $offset || ($required[$code] ?? 0) < 1 || in_array($credential['entry_id'], $events, true) || in_array($credential['title_id'], $sources, true)
                || ($judges > 0 && trim((string) ($credential['judgeId'] ?? '')) === '')) {
                continue;
            }
            $candidates[$index] = $credential;
            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }
        foreach ($required as $code => $count) {
            if (($counts[$code] ?? 0) < $count) {
                return [];
            }
        }
        if (count(array_unique(array_column($candidates, $distinctEvents ? 'entry_id' : 'title_id'))) < $remaining
            || $this->distinct([...$selected, ...array_values($candidates)], 'judgeId') < $judges
            || $this->distinct([...$selected, ...array_values($candidates)], 'region') < $regions
            || ! $this->canCoverJudgesAndRegions($selected, array_values($candidates), $judges, $regions, $remaining)) {
            return [];
        }
        foreach ($candidates as $index => $credential) {
            $next = $required;
            $next[$this->requiredCode($credential['code'], $required)]--;
            $proof = $this->select($pool, $next, $judges, $regions, [...$selected, $credential], $index + 1, $distinctEvents);
            if ($proof !== []) {
                return $proof;
            }
        }

        return [];
    }

    /**
     * @param  list<array<string,mixed>>  $selected
     * @param  list<array<string,mixed>>  $candidates
     */
    private function canCoverJudgesAndRegions(array $selected, array $candidates, int $judges, int $regions, int $remaining): bool
    {
        $neededJudges = max(0, $judges - $this->distinct($selected, 'judgeId'));
        $neededRegions = max(0, $regions - $this->distinct($selected, 'region'));
        $requiredPairs = $neededJudges + $neededRegions - $remaining;
        if ($requiredPairs <= 0 || $neededJudges === 0 || $neededRegions === 0) {
            return true;
        }
        $knownJudges = array_fill_keys(array_column($selected, 'judgeId'), true);
        $knownRegions = array_fill_keys(array_column($selected, 'region'), true);
        $edges = [];
        foreach ($candidates as $credential) {
            $judge = trim((string) ($credential['judgeId'] ?? ''));
            $region = trim((string) ($credential['region'] ?? ''));
            if ($judge !== '' && $region !== '' && ! isset($knownJudges[$judge]) && ! isset($knownRegions[$region])) {
                $edges['judge:'.$judge]['region:'.$region] = true;
            }
        }
        $matched = [];
        foreach (array_keys($edges) as $judge) {
            $visited = [];
            if ($this->matchRegion($judge, $edges, $matched, $visited) && count($matched) >= $requiredPairs) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string,array<string,true>>  $edges
     * @param  array<string,string>  $matched
     * @param  array<string,true>  $visited
     */
    private function matchRegion(string $judge, array $edges, array &$matched, array &$visited): bool
    {
        foreach (array_keys($edges[$judge] ?? []) as $region) {
            if (isset($visited[$region])) {
                continue;
            }
            $visited[$region] = true;
            if (! isset($matched[$region]) || $this->matchRegion($matched[$region], $edges, $matched, $visited)) {
                $matched[$region] = $judge;

                return true;
            }
        }

        return false;
    }

    /** @param array<string,int> $required */
    private function requiredCode(string $code, array $required): string
    {
        return isset($required['SPORT_NATIONAL_DIRECT_PROOF']) && in_array($code, ['SPORT_CH_RUS', 'SPORT_CH_RKF', 'SPORT_CUP_RUS'], true)
            ? 'SPORT_NATIONAL_DIRECT_PROOF' : $code;
    }

    /**
     * @param  list<array<string,mixed>>  $history
     * @param  list<string>  $codes
     * @return list<array<string,mixed>>
     */
    private function withCodes(array $history, array $codes, string $kind): array
    {
        return array_values(array_filter($history, fn (array $credential): bool => ($credential['kind'] ?? '') === $kind && in_array($credential['code'], $codes, true)));
    }

    /** @param list<array<string,mixed>> $credentials */
    private function distinct(array $credentials, string $key): int
    {
        return count(array_unique(array_filter(array_map(fn (array $credential): string => trim((string) ($credential[$key] ?? '')), $credentials), fn (string $value): bool => $value !== '')));
    }

    /** @param array<string,mixed> $credential */
    private function region(array $credential): string
    {
        $region = ($credential['code'] ?? '') === 'SPORT_REGIONAL_CH'
            ? ($credential['details']['region'] ?? $credential['region'] ?? '')
            : ($credential['region'] ?? '');

        return mb_strtolower(trim((string) $region));
    }

    /**
     * @param  non-empty-list<array<string,mixed>>  $proof
     * @param  array<string,mixed>  $details
     * @return array<string,mixed>
     */
    private function award(string $code, array $proof, string $path, string $scope = '', array $details = []): array
    {
        $last = $proof[array_key_last($proof)];

        return ['entry_id' => $last['entry_id'], 'code' => $code, 'kind' => 'championship', 'scope_key' => $scope,
            'details' => [...$details, 'credential_title_ids' => array_map(fn (array $credential): int => (int) $credential['title_id'], $proof),
                'credential_entry_ids' => array_values(array_unique(array_column($proof, 'entry_id'))), 'exchange_path' => $path,
                'proof_purpose' => 'sport_exchange', 'policy_version' => 2, 'source_keys' => $this->balance['source_keys'] ?? ['rkf_sport_titles']]];
    }
}
