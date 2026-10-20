<?php

namespace App\Modules\Pets\Calculators;

final class SpecialExhibitionChampionships
{
    /** @param array<string,mixed> $balance */
    public function __construct(private EventChampionshipRules $legacy, private array $balance) {}

    /**
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $credentials
     * @return list<array<string,mixed>>
     */
    public function calculate(array $profile, array $credentials, int $at): array
    {
        $history = array_values(array_filter($credentials, fn (array $receipt): bool => ($receipt['discipline'] ?? '') === 'conformation'
            && ($receipt['awarded_at'] ?? PHP_INT_MAX) <= $at && ($receipt['title_id'] ?? 0) > 0));
        usort($history, fn (array $first, array $second): int => [$first['awarded_at'], $first['title_id']] <=> [$second['awarded_at'], $second['title_id']]);
        $awards = [];
        $standardHistory = $history;
        $standardProfile = $profile;
        if (! ($profile['has_nkp'] ?? true)) {
            $clubCodes = ['KCHK', 'JKCHK', 'VKCHK', 'CH_CLUB', 'JCH_CLUB', 'VCH_CLUB'];
            $standardHistory = array_values(array_filter($history, fn (array $receipt): bool => ! in_array($receipt['code'], $clubCodes, true)));
            $standardProfile['championships'] = array_values(array_diff($profile['championships'] ?? [], $clubCodes));
        }
        foreach ($this->legacy->calculate('conformation', $standardProfile, $this->legacyReceipts($standardHistory), $at) as $award) {
            if (in_array($award['code'], ['GRAND_CH_RUS', 'JGRAND_CH_RUS', 'VGRAND_CH_RUS'], true)) {
                continue;
            }
            $proof = $standardHistory;
            foreach (array_reverse(array_keys($proof)) as $index) {
                $reduced = $proof;
                unset($reduced[$index]);
                if (in_array($award['code'], array_column($this->legacy->calculate('conformation', $standardProfile, $this->legacyReceipts(array_values($reduced)), $at), 'code'), true)) {
                    $proof = $reduced;
                }
            }
            $this->grant($awards, $history, $profile, $award['code'], array_values($proof), 'standard', '');
        }
        if (! ($profile['registered'] ?? false) || ! ($profile['recognized_rkf'] ?? false)) {
            return $awards;
        }
        $validity = ($this->balance['regulations']['certificate_validity_months'] ?? 60) * PetAgeRules::SECONDS_PER_MONTH;
        $national = array_values(array_filter($history, fn (array $receipt): bool => ($receipt['country'] ?? '') === 'RU'
            && $receipt['awarded_at'] >= $at - $validity));
        if (($profile['pedigree_complete'] ?? false) || ($profile['pedigree_exempt'] ?? false)) {
            $this->nationalExchanges($awards, $national, $history, $profile);
            $this->clubExchanges($awards, $national, $history, $profile);
            $secondComponent = ($profile['has_nkp'] ?? true) ? 'CLUB' : 'BREED';
            foreach (['GRAND_CH_RUS' => ['CH_RUS', 'CH_'.$secondComponent], 'JGRAND_CH_RUS' => ['JCH_RUS', 'JCH_'.$secondComponent], 'VGRAND_CH_RUS' => ['VCH_RUS', 'VCH_'.$secondComponent]] as $code => $required) {
                $proof = [];
                foreach ($required as $requiredCode) {
                    $receipts = array_values(array_filter($history, fn (array $receipt): bool => $receipt['code'] === $requiredCode && ($receipt['kind'] ?? '') === 'championship'));
                    if ($receipts !== []) {
                        $proof[] = $receipts[0];
                    }
                }
                if (count($proof) === count($required)) {
                    $this->grant($awards, $history, $profile, $code, $proof, 'national_and_breed_championships', '');
                }
            }
        }
        $policies = $this->balance['special_exhibitions'] ?? [];
        foreach ($policies['absolute_winners'] ?? [] as $code => $policy) {
            $first = $this->available($national, $history, $code);
            foreach ($first as $receipt) {
                if (($receipt['code'] ?? '') !== $policy['codes'][0] || ! $this->special($receipt, $policy['series'])
                    || ($receipt['details']['showLeg'] ?? '') !== $policy['legs'][0]) {
                    continue;
                }
                foreach ($first as $other) {
                    if (($other['code'] ?? '') === $policy['codes'][1] && $this->special($other, $policy['series'])
                        && ($other['details']['showLeg'] ?? '') === $policy['legs'][1]
                        && $receipt['entry_id'] !== $other['entry_id']
                        && $receipt['details']['showYear'] === $other['details']['showYear']
                        && $receipt['details']['showEdition'] === $other['details']['showEdition']) {
                        $this->grant($awards, $history, $profile, $code, [$receipt, $other], 'paired_show_winners', 'edition:'.$receipt['details']['showEdition'],
                            ['showYear' => $receipt['details']['showYear'], 'showEdition' => $receipt['details']['showEdition'], 'specialShow' => $policy['series']]);
                        break;
                    }
                }
            }
        }
        foreach ($policies['grand_championships'] ?? [] as $code => $policy) {
            $receipts = array_values(array_filter($this->available($national, $history, $code), fn (array $receipt): bool => $receipt['code'] === $policy['certificate']
                && ($this->special($receipt, 'eurasia') || $this->special($receipt, 'russia'))));
            $proof = $this->distinctProof($receipts, $policy['count']);
            if ($proof !== []) {
                $this->grant($awards, $history, $profile, $code, $proof, 'named_show_rkf_certificates', $this->issueScope($proof));
            }
        }
        foreach ($policies['absolute_championships'] ?? [] as $code => $required) {
            $proof = [];
            foreach ($required as $requiredCode) {
                $receipts = array_values(array_filter($this->available($national, $history, $code), fn (array $receipt): bool => $receipt['code'] === $requiredCode && ($receipt['kind'] ?? '') === 'championship'));
                if ($receipts !== []) {
                    $proof[] = $receipts[0];
                }
            }
            if (count($proof) === count($required)) {
                $this->grant($awards, $history, $profile, $code, $proof, 'absolute_show_winners', $this->issueScope($proof));
            }
        }

        return $awards;
    }

    /** @param list<array<string,mixed>> $awards
     * @param  list<array<string,mixed>>  $national
     * @param  list<array<string,mixed>>  $history
     * @param  array<string,mixed>  $profile
     */
    private function nationalExchanges(array &$awards, array $national, array $history, array $profile): void
    {
        foreach (['CH_RUS' => 'CAC', 'JCH_RUS' => 'JCAC', 'VCH_RUS' => 'VCAC'] as $code => $certificate) {
            $receipts = array_values(array_filter($national, fn (array $receipt): bool => $receipt['code'] === $certificate && $this->doubled($receipt, $certificate)));
            $proof = $this->distinctProof($receipts, 2);
            if ($proof !== []) {
                $this->grant($awards, $history, $profile, $code, $proof, 'two_doubled_certificates', '');
            }
        }
        $winners = array_filter($national, fn (array $receipt): bool => ($receipt['code'] === 'EAW' && $this->special($receipt, 'eurasia'))
            || ($receipt['code'] === 'RW' && $this->special($receipt, 'russia')));
        foreach ($winners as $winner) {
            foreach ($national as $receipt) {
                if ($receipt['code'] === 'CAC' && $this->differentJudge($winner, $receipt) && $winner['entry_id'] !== $receipt['entry_id']) {
                    $this->grant($awards, $history, $profile, 'CH_RUS', [$winner, $receipt], 'named_winner_and_other_cac', '');

                    return;
                }
            }
        }
    }

    /** @param list<array<string,mixed>> $awards
     * @param  list<array<string,mixed>>  $national
     * @param  list<array<string,mixed>>  $history
     * @param  array<string,mixed>  $profile
     */
    private function clubExchanges(array &$awards, array $national, array $history, array $profile): void
    {
        $hasClub = (bool) ($profile['has_nkp'] ?? true);
        $series = $hasClub ? 'national_club' : 'national_breed';
        $certificate = $hasClub ? 'KCHK' : 'KCHP';
        $title = $hasClub ? 'CH_CLUB' : 'CH_BREED';
        $winner = $hasClub ? 'CLUB_WINNER' : 'BREED_WINNER';
        if (! ($profile['working_breed'] ?? false) || ($profile['working_certificate'] ?? false)) {
            foreach ($national as $receipt) {
                if ($receipt['code'] !== $winner || ! $this->special($receipt, $series)) {
                    continue;
                }
                foreach ($national as $other) {
                    if ($other['code'] === $certificate && $this->differentJudge($receipt, $other) && $receipt['entry_id'] !== $other['entry_id']) {
                        $this->grant($awards, $history, $profile, $title, [$receipt, $other], 'annual_winner_and_other_club_certificate', '');
                        break;
                    }
                }
            }
        }
        if (! $hasClub) {
            foreach (['CH_BREED' => ['KCHP', 4], 'JCH_BREED' => ['JKCHP', 3], 'VCH_BREED' => ['VKCHP', 3]] as $code => [$candidate, $count]) {
                $receipts = array_values(array_filter($national, fn (array $receipt): bool => $receipt['code'] === $candidate));
                $proof = $this->distinctProof($receipts, $count);
                if ($proof !== [] && ($code !== 'CH_BREED' || ! ($profile['working_breed'] ?? false) || ($profile['working_certificate'] ?? false))) {
                    $this->grant($awards, $history, $profile, $code, $proof, 'breed_certificates', '');
                }
            }
        }
    }

    /** @param array<string,mixed> $receipt */
    private function special(array $receipt, string $series): bool
    {
        $policy = $this->balance['special_exhibitions']['shows'][$series] ?? null;
        $details = $receipt['details'] ?? [];

        return is_array($policy) && ($receipt['regulation_version'] ?? 1) >= 2 && ($receipt['country'] ?? '') === 'RU'
            && ($receipt['rank'] ?? '') === $policy['rank'] && ($details['specialShow'] ?? '') === $series
            && in_array($details['showLeg'] ?? '', $policy['legs'], true) && is_int($details['showYear'] ?? null)
            && is_string($details['showEdition'] ?? null)
            && preg_match('/^'.preg_quote((string) $details['showYear'], '/').'-(0[1-9]|1[0-2])$/D', $details['showEdition']) === 1;
    }

    /** @param array<string,mixed> $receipt */
    private function doubled(array $receipt, string $certificate): bool
    {
        foreach ($this->balance['special_exhibitions']['shows'] ?? [] as $series => $policy) {
            if (in_array($certificate, $policy['double_certificates'], true) && $this->special($receipt, $series)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string,mixed>> $receipts
     * @return list<array<string,mixed>>
     */
    private function distinctProof(array $receipts, int $count): array
    {
        $proof = [];
        $judges = [];
        $entries = [];
        foreach ($receipts as $receipt) {
            $judge = $receipt['judgeId'] ?? '';
            if (! is_string($judge) || $judge === '' || isset($judges[$judge]) || isset($entries[$receipt['entry_id']])) {
                continue;
            }
            $proof[] = $receipt;
            $judges[$judge] = true;
            $entries[$receipt['entry_id']] = true;
            if (count($proof) === $count) {
                return $proof;
            }
        }

        return [];
    }

    /** @param array<string,mixed> $first
     * @param  array<string,mixed>  $second
     */
    private function differentJudge(array $first, array $second): bool
    {
        return is_string($first['judgeId'] ?? null) && $first['judgeId'] !== ''
            && is_string($second['judgeId'] ?? null) && $second['judgeId'] !== '' && $first['judgeId'] !== $second['judgeId'];
    }

    /** @param list<array<string,mixed>> $receipts
     * @param  list<array<string,mixed>>  $history
     * @return list<array<string,mixed>>
     */
    private function available(array $receipts, array $history, string $purpose): array
    {
        $used = [];
        foreach ($history as $receipt) {
            if (($receipt['kind'] ?? '') === 'championship' && in_array($receipt['details']['proof_purpose'] ?? $receipt['code'], [$purpose, 'show:'.$purpose], true)) {
                foreach ($receipt['details']['credential_title_ids'] ?? [] as $id) {
                    $used[$id] = true;
                }
            }
        }

        return array_values(array_filter($receipts, fn (array $receipt): bool => ! isset($used[$receipt['title_id']])));
    }

    /** @param list<array<string,mixed>> $proof */
    private function issueScope(array $proof): string
    {
        $ids = array_column($proof, 'title_id');
        sort($ids);

        return 'issue:'.substr(hash('sha256', implode(':', $ids)), 0, 32);
    }

    /** @param list<array<string,mixed>> $awards
     * @param  list<array<string,mixed>>  $history
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $proof
     * @param  array<string,mixed>  $details
     */
    private function grant(array &$awards, array $history, array $profile, string $code, array $proof, string $path, string $scope, array $details = []): void
    {
        if ($proof === [] || ($scope === '' && in_array($code, $profile['championships'] ?? [], true))) {
            return;
        }
        foreach ([...$history, ...$awards] as $existing) {
            if (($existing['kind'] ?? '') === 'championship' && $existing['code'] === $code && ($existing['scope_key'] ?? '') === $scope) {
                return;
            }
        }
        usort($proof, fn (array $first, array $second): int => [$first['awarded_at'], $first['title_id']] <=> [$second['awarded_at'], $second['title_id']]);
        $last = $proof[array_key_last($proof)];
        $awards[] = ['entry_id' => $last['entry_id'], 'code' => $code, 'kind' => 'championship', 'scope_key' => $scope,
            'details' => [...$details, 'credential_title_ids' => array_values(array_unique(array_column($proof, 'title_id'))),
                'credential_entry_ids' => array_values(array_unique(array_column($proof, 'entry_id'))), 'exchange_path' => $path, 'proof_purpose' => 'show:'.$code,
                'source_url' => $this->balance['special_exhibitions']['sources']['titles'] ?? null]];
    }

    /** @param list<array<string,mixed>> $history
     * @return list<array<string,mixed>>
     */
    private function legacyReceipts(array $history): array
    {
        $receipts = [];
        $nationalCodes = ['CAC', 'JCAC', 'VCAC', 'R_CAC', 'R_JCAC', 'R_VCAC', 'CH_RKF_CERT', 'JCH_RKF_CERT', 'VCH_RKF_CERT', 'KCHK', 'JKCHK', 'VKCHK'];
        foreach ($history as $receipt) {
            if (in_array($receipt['code'], $nationalCodes, true) && ($receipt['country'] ?? '') !== 'RU') {
                continue;
            }
            if (in_array($receipt['code'], ['R_CAC', 'R_JCAC', 'R_VCAC'], true) && $this->doubled($receipt, substr($receipt['code'], 2))) {
                $receipt['code'] = substr($receipt['code'], 2);
            }
            $receipts[] = $receipt;
        }

        return $receipts;
    }
}
