<?php

namespace App\Modules\Pets\Calculators;

final class SpecialExhibitionAwards
{
    /** @param array<string,mixed> $balance */
    public function __construct(private RegulatedExhibitionAwards $base, private array $balance) {}

    /**
     * @param  array<string,mixed>  $regulation
     * @param  list<array<string,mixed>>  $entries
     * @return list<array<string,mixed>>
     */
    public function calculate(array $regulation, array $entries): array
    {
        $policy = $this->policy($regulation);
        if ($policy === null) {
            $awards = $this->base->calculate($regulation, $entries);
            if (($regulation['version'] ?? 1) < 2 || ($regulation['rank'] ?? '') !== 'specialty'
                || ($regulation['system'] ?? '') !== 'rkf' || ($regulation['country'] ?? '') !== 'RU') {
                return $awards;
            }
            $entries = array_column($entries, null, 'id');
            foreach ($awards as &$award) {
                $entry = $entries[$award['entry_id']];
                $profile = $entry['snapshot']['profile'] ?? $entry['snapshot'];
                if (! ($profile['has_nkp'] ?? true)) {
                    $award['code'] = in_array($award['code'], ['R_KCHK', 'R_JKCHK', 'R_VKCHK'], true)
                        ? $this->reserveCode($award['code'], false) : strtr($award['code'], ['KCHK' => 'KCHP']);
                }
            }
            unset($award);

            return $awards;
        }
        $national = in_array($regulation['specialShow'], ['national_club', 'national_breed'], true);
        if ($national) {
            $entries = array_values(array_filter($entries, function (array $entry) use ($regulation): bool {
                $profile = $entry['snapshot']['profile'] ?? $entry['snapshot'];

                return ($profile['breed_id'] ?? null) === ($regulation['breedId'] ?? null)
                    && (bool) ($profile['has_nkp'] ?? true) === ($regulation['specialShow'] === 'national_club');
            }));
        }
        $awards = $this->base->calculate($national ? [...$regulation, 'rank' => 'cac_rkf_special'] : $regulation, $entries);
        if ($national) {
            $awards = array_values(array_filter($awards, fn (array $award): bool => ! in_array($award['code'], ['CH_RKF_CERT', 'JCH_RKF_CERT', 'VCH_RKF_CERT'], true)
                && ! str_starts_with($award['code'], 'BIG_') && ! str_starts_with($award['code'], 'BIS_')));
            foreach ($awards as &$award) {
                $award['code'] = strtr($award['code'], ['JCAC' => 'JKCHK', 'VCAC' => 'VKCHK', 'CAC' => 'KCHK']);
            }
            unset($award);
        }
        $entries = array_column($entries, null, 'id');
        $metadata = array_intersect_key($regulation, array_flip(['specialShow', 'showLeg', 'showYear', 'showEdition', 'sources']));
        $metadata['source_url'] = $this->balance['special_exhibitions']['sources'][$policy['source']];
        $additional = [];
        foreach ($awards as &$award) {
            $entry = $entries[$award['entry_id']];
            $profile = $entry['snapshot']['profile'] ?? $entry['snapshot'];
            $award['details'] = [...($award['details'] ?? []), ...$metadata];
            if (! ($profile['registered'] ?? false) || ! ($profile['recognized_rkf'] ?? false)) {
                continue;
            }
            if ($national && ($profile['breed_id'] ?? null) !== ($regulation['breedId'] ?? null)) {
                continue;
            }
            $class = $entry['plan']['exhibition_class'] ?? '';
            $originalCode = $award['code'];
            $hasClub = (bool) ($profile['has_nkp'] ?? true);
            if ($national) {
                if ($regulation['specialShow'] === 'national_breed' || ! $hasClub) {
                    $award['code'] = strtr($award['code'], ['KCHK' => 'KCHP']);
                }
                if (in_array($originalCode, ['KCHK', 'JKCHK', 'VKCHK'], true)) {
                    $award['details']['club_certificate_weight'] = 2;
                }
                if (in_array($originalCode, ['R_KCHK', 'R_JKCHK', 'R_VKCHK'], true)) {
                    $award['code'] = $this->reserveCode($originalCode, $regulation['specialShow'] === 'national_club' && $hasClub);
                }
            }
            if (in_array($originalCode, $policy['double_certificates'], true)) {
                $award['details']['national_certificate_weight'] = 2;
            }
            if (in_array($originalCode, ['R_CAC', 'R_JCAC', 'R_VCAC'], true)
                && in_array(substr($originalCode, 2), $policy['double_certificates'], true)) {
                $award['details']['national_certificate_weight'] = 1;
            }
            $winnerCodes = $policy['winner'];
            if (($regulation['specialShow'] ?? '') === 'russia' && $regulation['showLeg'] === 'moscow_cup') {
                $winnerCodes = ['MW', 'MJW', 'MVW'];
            }
            $index = match (true) {
                in_array($originalCode, ['BEST_MALE', 'BEST_FEMALE'], true) => 0,
                $originalCode === 'CW' && $class === 'junior' => 1,
                $originalCode === 'CW' && $class === 'veteran' => 2,
                default => null,
            };
            if ($index !== null && isset($winnerCodes[$index])) {
                $additional[] = $this->award($entry, $winnerCodes[$index], 'comparison', $metadata);
                if ($regulation['specialShow'] === 'eurasia' && $regulation['showLeg'] === 'cis') {
                    $additional[] = $this->award($entry, ['CIS_W', 'CIS_JW', 'CIS_VW'][$index], 'comparison', $metadata);
                }
            }
            if (! $national && $originalCode === 'CW' && in_array($class, ['baby', 'puppy'], true)
                && in_array($regulation['specialShow'], ['eurasia', 'russia'], true)) {
                $additional[] = $this->award($entry, $class === 'baby' ? 'BABY_RKF_WINNER' : 'PUPPY_RKF_WINNER', 'comparison', $metadata);
            }
            if (($policy['specialties'] ?? false)
                && ! in_array($profile['breed_code'] ?? '', $this->balance['special_exhibitions']['specialty_excluded_breeds'] ?? [], true)) {
                $code = match ($originalCode) {
                    'CAC' => $hasClub ? 'KCHK' : 'KCHP', 'JCAC' => $hasClub ? 'JKCHK' : 'JKCHP', 'VCAC' => $hasClub ? 'VKCHK' : 'VKCHP',
                    'R_CAC', 'R_JCAC', 'R_VCAC' => $this->reserveCode($originalCode, $hasClub),
                    default => null,
                };
                if ($code !== null) {
                    $additional[] = $this->award($entry, $code, 'certificate', $metadata);
                }
            }
        }
        unset($award);

        return [...$awards, ...$additional];
    }

    /** @param array<string,mixed> $regulation
     * @return array<string,mixed>|null
     */
    private function policy(array $regulation): ?array
    {
        $policy = $this->balance['special_exhibitions']['shows'][$regulation['specialShow'] ?? ''] ?? null;
        if (! is_array($policy) || ($regulation['version'] ?? 1) < 2 || ($regulation['country'] ?? '') !== 'RU'
            || ($regulation['system'] ?? '') !== 'rkf' || ($regulation['rank'] ?? '') !== $policy['rank']
            || ! in_array($regulation['showLeg'] ?? '', $policy['legs'], true)
            || ! is_int($regulation['showYear'] ?? null) || ! is_string($regulation['showEdition'] ?? null)
            || preg_match('/^'.preg_quote((string) $regulation['showYear'], '/').'-(0[1-9]|1[0-2])$/D', $regulation['showEdition']) !== 1) {
            return null;
        }

        return $policy;
    }

    private function reserveCode(string $code, bool $hasClub): string
    {
        $prefix = str_contains($code, 'J') ? 'J' : (str_contains($code, 'V') ? 'V' : '');

        return $prefix.'CC'.($hasClub ? '' : '_BREED');
    }

    /** @param array<string,mixed> $entry
     * @param  array<string,mixed>  $details
     * @return array<string,mixed>
     */
    private function award(array $entry, string $code, string $kind, array $details): array
    {
        return ['entry_id' => $entry['id'], 'code' => $code, 'kind' => $kind, 'details' => $details];
    }
}
