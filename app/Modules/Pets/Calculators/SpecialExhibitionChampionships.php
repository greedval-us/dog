<?php

namespace App\Modules\Pets\Calculators;

final class SpecialExhibitionChampionships
{
    private const array BREED_TITLE_RULES = ['CH_BREED' => ['KCHP', 4], 'JCH_BREED' => ['JKCHP', 3], 'VCH_BREED' => ['VKCHP', 3]];

    /** @param array<string,mixed> $balance */
    public function __construct(private EventChampionshipRules $legacy, private array $balance) {}

    /**
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $credentials
     * @return list<array<string,mixed>>
     */
    public function calculate(array $profile, array $credentials, int $at): array
    {
        $history = $this->history($credentials, $at);
        $awards = [];
        ['history' => $standardHistory, 'profile' => $standardProfile] = $this->standardContext($history, $profile);
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

    /**
     * @param  array<string,mixed>  $profile
     * @param  list<array<string,mixed>>  $credentials
     * @return list<array<string,mixed>>
     */
    public function progress(array $profile, array $credentials, int $at): array
    {
        $history = $this->history($credentials, $at);
        ['history' => $standardHistory, 'profile' => $standardProfile] = $this->standardContext($history, $profile);
        $awards = $this->calculate($profile, $credentials, $at);
        $goals = [];
        foreach ($this->legacy->progress('conformation', $standardProfile, $this->legacyReceipts($standardHistory), $at) as $goal) {
            if (in_array($goal['code'], ['GRAND_CH_RUS', 'JGRAND_CH_RUS', 'VGRAND_CH_RUS'], true)
                || (! ($profile['has_nkp'] ?? true) && in_array($goal['code'], ['CH_CLUB', 'JCH_CLUB', 'VCH_CLUB'], true))) {
                continue;
            }
            $goals[$goal['code']] = $this->legacy->progressGoal($goal['code'], $goal['paths'], $profile, $awards);
        }
        $validity = ($this->balance['regulations']['certificate_validity_months'] ?? 60) * PetAgeRules::SECONDS_PER_MONTH;
        $validAfter = $at - $validity;
        $national = array_values(array_filter($history, fn (array $receipt): bool => ($receipt['country'] ?? '') === 'RU' && $receipt['awarded_at'] >= $validAfter));
        $gates = $this->legacy->progressProfileRequirements([...$profile, 'pedigree_complete' => ($profile['pedigree_complete'] ?? false) || ($profile['pedigree_exempt'] ?? false)], 'rkf');
        foreach (['CH_RUS' => 'CAC', 'JCH_RUS' => 'JCAC', 'VCH_RUS' => 'VCAC'] as $code => $certificate) {
            $receipts = array_values(array_filter($national, fn (array $receipt): bool => $receipt['code'] === $certificate && $this->doubled($receipt, $certificate)));
            $target = [...$this->legacy->progressTarget($receipts, [$certificate], $this->doubledCertificateCount()), 'specialShow' => $this->doubledShows($certificate)];
            $paths = $goals[$code]['paths'] ?? [];
            $paths[] = $this->legacy->progressPath('two_doubled_certificates', $receipts,
                [...$gates, ...$this->legacy->progressReceiptRequirements($receipts, [$certificate], $this->doubledCertificateCount(), $this->doubledCertificateCount())], [$target],
                $this->legacy->progressExcluded($history, $receipts, [$certificate], $validAfter));
            $goals[$code] = $this->legacy->progressGoal($code, $paths, $profile, $awards);
        }
        $winners = array_values(array_filter($national, fn (array $receipt): bool => ($receipt['code'] === 'EAW' && $this->special($receipt, 'eurasia'))
            || ($receipt['code'] === 'RW' && $this->special($receipt, 'russia'))));
        $winner = $winners[0] ?? null;
        foreach ($winners as $candidate) {
            if (array_filter($national, fn (array $receipt): bool => $receipt['code'] === 'CAC' && $receipt['entry_id'] !== $candidate['entry_id'] && $this->differentJudge($candidate, $receipt)) !== []) {
                $winner = $candidate;
                break;
            }
        }
        $cac = array_values(array_filter($national, fn (array $receipt): bool => $receipt['code'] === 'CAC'
            && ($winner === null || ($receipt['entry_id'] !== $winner['entry_id'] && $this->differentJudge($winner, $receipt)))));
        $paths = $goals['CH_RUS']['paths'];
        $targets = [];
        if ($winner === null) {
            $targets[] = ['codes' => ['EAW', 'RW'], 'specialShow' => ['eurasia', 'russia']];
        }
        if ($cac === []) {
            $targets[] = ['codes' => ['CAC'], 'excludeJudges' => $winner === null ? [] : [$winner['judgeId']]];
        }
        $paths[] = $this->legacy->progressPath('named_winner_and_other_cac', [...($winner === null ? [] : [$winner]), ...$cac],
            [...$gates, $this->legacy->progressRequirement('certificates', 'count', (int) ($winner !== null), 1, ['EAW', 'RW']),
                $this->legacy->progressRequirement('certificates', 'count', (int) ($cac !== []), 1, ['CAC'])], $targets);
        $goals['CH_RUS'] = $this->legacy->progressGoal('CH_RUS', $paths, $profile, $awards);

        $hasClub = (bool) ($profile['has_nkp'] ?? true);
        $series = $hasClub ? 'national_club' : 'national_breed';
        $clubCertificate = $hasClub ? 'KCHK' : 'KCHP';
        $clubTitle = $hasClub ? 'CH_CLUB' : 'CH_BREED';
        $clubWinner = $hasClub ? 'CLUB_WINNER' : 'BREED_WINNER';
        foreach ($hasClub ? [$clubTitle => [$clubCertificate, 0]] : self::BREED_TITLE_RULES as $code => [$certificate, $count]) {
            $paths = $goals[$code]['paths'] ?? [];
            if (! $hasClub) {
                $receipts = array_values(array_filter($national, fn (array $receipt): bool => $receipt['code'] === $certificate));
                $paths[] = $this->legacy->progressPath('breed_certificates', $receipts,
                    [...$gates, ...$this->legacy->progressReceiptRequirements($receipts, [$certificate], $count, $count),
                        $this->workingRequirement($profile, $code === $clubTitle)],
                    [$this->legacy->progressTarget($receipts, [$certificate], $count)],
                    $this->legacy->progressExcluded($history, $receipts, [$certificate], $validAfter));
            }
            if ($code === $clubTitle) {
                $winners = array_values(array_filter($national, fn (array $receipt): bool => $receipt['code'] === $clubWinner && $this->special($receipt, $series)));
                $winner = $winners[0] ?? null;
                foreach ($winners as $candidate) {
                    if (array_filter($national, fn (array $receipt): bool => $receipt['code'] === $clubCertificate && $receipt['entry_id'] !== $candidate['entry_id'] && $this->differentJudge($candidate, $receipt)) !== []) {
                        $winner = $candidate;
                        break;
                    }
                }
                $other = array_values(array_filter($national, fn (array $receipt): bool => $receipt['code'] === $clubCertificate
                    && ($winner === null || ($winner['entry_id'] !== $receipt['entry_id'] && $this->differentJudge($winner, $receipt)))));
                $targets = [];
                if ($winner === null) {
                    $targets[] = ['codes' => [$clubWinner], 'specialShow' => [$series]];
                }
                if ($other === []) {
                    $targets[] = ['codes' => [$clubCertificate], 'excludeJudges' => $winner === null ? [] : [$winner['judgeId']]];
                }
                $paths[] = $this->legacy->progressPath('annual_winner_and_other_club_certificate', [...($winner === null ? [] : [$winner]), ...$other],
                    [...$gates, $this->workingRequirement($profile, true),
                        $this->legacy->progressRequirement('titles', 'document', (int) ($winner !== null), 1, [$clubWinner]),
                        $this->legacy->progressRequirement('certificates', 'count', (int) ($other !== []), 1, [$clubCertificate])],
                    $targets);
            }
            $goals[$code] = $this->legacy->progressGoal($code, $paths, $profile, $awards);
        }
        $component = $hasClub ? 'CLUB' : 'BREED';
        foreach (['GRAND_CH_RUS' => ['CH_RUS', 'CH_'.$component], 'JGRAND_CH_RUS' => ['JCH_RUS', 'JCH_'.$component], 'VGRAND_CH_RUS' => ['VCH_RUS', 'VCH_'.$component]] as $code => $required) {
            $receipts = array_values(array_filter($history, fn (array $receipt): bool => ($receipt['kind'] ?? '') === 'championship' && in_array($receipt['code'], $required, true)));
            $requirements = [...$gates];
            foreach ($required as $certificate) {
                $requirements[] = $this->legacy->progressRequirement('titles', 'document', (int) in_array($certificate, array_column($receipts, 'code'), true), 1, [$certificate]);
            }
            $missing = array_values(array_diff($required, array_column($receipts, 'code')));
            $goals[$code] = $this->legacy->progressGoal($code, [$this->legacy->progressPath('national_and_breed_championships', $receipts, $requirements, $missing === [] ? [] : [['codes' => $missing]])], $profile, $awards);
        }

        $specialGates = $this->legacy->progressProfileRequirements($profile, 'rkf', false);
        $policies = $this->balance['special_exhibitions'] ?? [];
        foreach ($policies['absolute_winners'] ?? [] as $code => $policy) {
            $available = array_values(array_filter($this->available($national, $history, $code), fn (array $receipt): bool => in_array($receipt['code'], $policy['codes'], true) && $this->special($receipt, $policy['series'])));
            $latest = $available === [] ? null : $available[array_key_last($available)];
            $edition = $profile['showEdition'] ?? $latest['details']['showEdition'] ?? null;
            $year = $profile['showYear'] ?? $latest['details']['showYear'] ?? null;
            $receipts = array_values(array_filter($available, fn (array $receipt): bool => ($edition === null || ($receipt['details']['showEdition'] ?? null) === $edition)
                && ($year === null || ($receipt['details']['showYear'] ?? null) === $year)));
            $requirements = [...$specialGates];
            $targets = [];
            foreach ($policy['legs'] as $index => $leg) {
                $legReceipts = array_values(array_filter($receipts, fn (array $receipt): bool => $receipt['code'] === $policy['codes'][$index] && ($receipt['details']['showLeg'] ?? '') === $leg));
                $requirements[] = [...$this->legacy->progressRequirement('certificates', 'count', (int) ($legReceipts !== []), 1, [$policy['codes'][$index]]), 'showLeg' => $leg];
                if ($legReceipts === []) {
                    $target = ['codes' => [$policy['codes'][$index]], 'specialShow' => [$policy['series']], 'showLeg' => [$leg]];
                    if ($edition !== null && $receipts !== []) {
                        $target['showEdition'] = $edition;
                    }
                    if ($year !== null && $receipts !== []) {
                        $target['showYear'] = $year;
                    }
                    $targets[] = $target;
                }
            }
            $scope = $edition === null ? '' : 'edition:'.$edition;
            $achieved = $scope !== '' && array_filter($history, fn (array $receipt): bool => ($receipt['kind'] ?? '') === 'championship' && $receipt['code'] === $code && ($receipt['scope_key'] ?? '') === $scope) !== [];
            $goals[$code] = $this->legacy->progressGoal($code, [$this->legacy->progressPath('paired_show_winners', $receipts, $requirements, $targets,
                $this->progressExcluded($history, $receipts, $policy['codes'], $validAfter, $code))], $profile, $awards, $scope, true, $achieved);
        }
        foreach ($policies['grand_championships'] ?? [] as $code => $policy) {
            $available = $this->available($national, $history, $code);
            $receipts = array_values(array_filter($available, fn (array $receipt): bool => $receipt['code'] === $policy['certificate']
                && ($this->special($receipt, 'eurasia') || $this->special($receipt, 'russia'))));
            $goals[$code] = $this->legacy->progressGoal($code, [$this->legacy->progressPath('named_show_rkf_certificates', $receipts,
                [...$specialGates, ...$this->legacy->progressReceiptRequirements($receipts, [$policy['certificate']], $policy['count'], $policy['count'])],
                [[...$this->legacy->progressTarget($receipts, [$policy['certificate']], $policy['count']), 'specialShow' => ['eurasia', 'russia']]],
                $this->progressExcluded($history, $receipts, [$policy['certificate']], $validAfter, $code))], $profile, $awards, repeatable: true);
        }
        foreach ($policies['absolute_championships'] ?? [] as $code => $required) {
            $receipts = array_values(array_filter($this->available($national, $history, $code), fn (array $receipt): bool => ($receipt['kind'] ?? '') === 'championship' && in_array($receipt['code'], $required, true)));
            $requirements = [...$specialGates];
            foreach ($required as $certificate) {
                $requirements[] = $this->legacy->progressRequirement('titles', 'document', (int) in_array($certificate, array_column($receipts, 'code'), true), 1, [$certificate]);
            }
            $missing = array_values(array_diff($required, array_column($receipts, 'code')));
            $goals[$code] = $this->legacy->progressGoal($code, [$this->legacy->progressPath('absolute_show_winners', $receipts, $requirements, $missing === [] ? [] : [['codes' => $missing]],
                $this->progressExcluded($history, $receipts, $required, $validAfter, $code))], $profile, $awards, repeatable: true);
        }

        return array_values($goals);
    }

    /** @param list<array<string,mixed>> $credentials
     * @return list<array<string,mixed>>
     */
    private function history(array $credentials, int $at): array
    {
        $history = array_values(array_filter($credentials, fn (array $receipt): bool => ($receipt['discipline'] ?? '') === 'conformation'
            && ($receipt['awarded_at'] ?? PHP_INT_MAX) <= $at && ($receipt['title_id'] ?? 0) > 0));
        usort($history, fn (array $first, array $second): int => [$first['awarded_at'], $first['title_id']] <=> [$second['awarded_at'], $second['title_id']]);

        return $history;
    }

    /** @param list<array<string,mixed>> $history
     * @param  array<string,mixed>  $profile
     * @return array{history:list<array<string,mixed>>,profile:array<string,mixed>}
     */
    private function standardContext(array $history, array $profile): array
    {
        if (! ($profile['has_nkp'] ?? true)) {
            $clubCodes = ['KCHK', 'JKCHK', 'VKCHK', 'CH_CLUB', 'JCH_CLUB', 'VCH_CLUB'];
            $history = array_values(array_filter($history, fn (array $receipt): bool => ! in_array($receipt['code'], $clubCodes, true)));
            $profile['championships'] = array_values(array_diff($profile['championships'] ?? [], $clubCodes));
        }

        return ['history' => $history, 'profile' => $profile];
    }

    /** @return list<string> */
    private function doubledShows(string $certificate): array
    {
        $shows = [];
        foreach ($this->balance['special_exhibitions']['shows'] ?? [] as $series => $policy) {
            if (in_array($certificate, $policy['double_certificates'], true)) {
                $shows[] = $series;
            }
        }

        return $shows;
    }

    /** @param array<string,mixed> $profile
     * @return array<string,mixed>
     */
    private function workingRequirement(array $profile, bool $adult): array
    {
        return $this->legacy->progressRequirement('working_certificate', 'document', (int) (! $adult || ! ($profile['working_breed'] ?? false) || ($profile['working_certificate'] ?? false)), 1);
    }

    /** @param list<array<string,mixed>> $history
     * @param  list<array<string,mixed>>  $accepted
     * @param  list<string>  $codes
     * @return list<array<string,mixed>>
     */
    private function progressExcluded(array $history, array $accepted, array $codes, int $validAfter, string $purpose): array
    {
        $unused = array_column($this->available($history, $history, $purpose), 'title_id');
        $used = array_values(array_diff(array_column($history, 'title_id'), $unused));

        return $this->legacy->progressExcluded($history, $accepted, $codes, $validAfter, $used);
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
            $proof = $this->distinctProof($receipts, $this->doubledCertificateCount());
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
            foreach (self::BREED_TITLE_RULES as $code => [$candidate, $count]) {
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

    private function doubledCertificateCount(): int
    {
        return $this->balance['special_exhibitions']['national_exchanges']['double_count'] ?? 2;
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
            if (in_array('show:'.$purpose, $receipt['details']['used_for'] ?? [], true)) {
                $used[$receipt['title_id']] = true;
            }
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
