<?php

namespace App\Modules\Pets\Calculators;

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
        if (! is_string($certificate) || ! ($profile['registered'] ?? false) || ! ($profile['recognized_rkf'] ?? false)) {
            return [];
        }
        $history = array_values(array_filter($credentials, fn (array $credential): bool => ($credential['discipline'] ?? '') === $discipline
            && (int) ($credential['awarded_at'] ?? PHP_INT_MAX) <= $at));
        usort($history, fn (array $first, array $second): int => [$first['awarded_at'], $first['entry_id'], $first['title_id'] ?? 0] <=> [$second['awarded_at'], $second['entry_id'], $second['title_id'] ?? 0]);
        $held = array_column(array_filter($history, fn (array $credential): bool => ($credential['kind'] ?? '') === 'championship'), 'code');
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
        $awards = [];
        $regions = [];
        foreach ($this->withCodes($available, ['REG_'.$certificate], 'certificate') as $credential) {
            if ((int) ($credential['regulation_version'] ?? 1) === 2 && $credential['region'] !== '') {
                $regions[$credential['region']][] = $credential;
            }
        }
        foreach ($regions as $region => $regional) {
            while (($proof = $this->select($regional, ['REG_'.$certificate => 3], 2)) !== []) {
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
                $pool = array_values(array_filter($available, fn (array $credential): bool => isset($rule['counts'][$this->requiredCode($credential['code'], $rule['counts'])])
                    && ($credential['kind'] ?? '') === (str_starts_with($credential['code'], 'SPORT_') ? 'championship' : 'certificate')));
                $proof = $this->select($pool, $rule['counts'], $rule['judges'], $rule['regions'], distinctEvents: $path <= 6);
                if ($proof !== []) {
                    $awards[] = $this->award('SPORT_NATIONAL_CH', $proof, 'national_'.$path);
                    break;
                }
            }
        }
        if (! in_array('SPORT_BREED_CH', $held, true)) {
            $proof = $this->select($this->withCodes($available, [$certificate.'_BREED'], 'certificate'), [$certificate.'_BREED' => 3], 2);
            if ($proof !== []) {
                $awards[] = $this->award('SPORT_BREED_CH', $proof, 'breed_3_certificates');
            }
        }

        return $awards;
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

    /** @return array<int,array{counts:array<string,int>,judges:int,regions:int}> */
    private function nationalPaths(string $certificate): array
    {
        return [
            1 => ['counts' => ['SPORT_CUP_RUS' => 1, 'SPORT_CH_RKF' => 1], 'judges' => 2, 'regions' => 0],
            2 => ['counts' => ['SPORT_CUP_RUS' => 2], 'judges' => 2, 'regions' => 0],
            3 => ['counts' => ['SPORT_CUP_RUS' => 1, $certificate => 2], 'judges' => 3, 'regions' => 0],
            4 => ['counts' => ['SPORT_CH_RKF' => 2], 'judges' => 2, 'regions' => 0],
            5 => ['counts' => ['SPORT_CH_RKF' => 1, $certificate => 2], 'judges' => 3, 'regions' => 0],
            6 => ['counts' => [$certificate => 5], 'judges' => 4, 'regions' => 3],
            7 => ['counts' => ['SPORT_REGIONAL_CH' => 2], 'judges' => 0, 'regions' => 2],
            8 => ['counts' => ['SPORT_REGIONAL_CH' => 1, 'SPORT_NATIONAL_DIRECT_PROOF' => 1], 'judges' => 0, 'regions' => 0],
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
