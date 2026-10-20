<?php

namespace App\Modules\Pets\Calculators;

final class RegulatedSportAwards
{
    /** @param array<string,mixed> $balance */
    public function __construct(private array $balance) {}

    /**
     * @param  array<string,mixed>  $regulation
     * @param  list<array<string,mixed>>  $entries
     * @return list<array<string,mixed>>
     */
    public function calculate(string $discipline, array $regulation, array $entries): array
    {
        $awards = [];
        $divisions = [];
        foreach ($entries as $entry) {
            $divisions[$entry['division']][] = $entry;
        }
        foreach ($divisions as $participants) {
            usort($participants, fn (array $first, array $second): int => ($first['rank'] ?? PHP_INT_MAX) <=> ($second['rank'] ?? PHP_INT_MAX));
            $raceNorm = $discipline === 'canicross' ? $this->raceNorm($participants) : null;
            foreach ($participants as $entry) {
                $result = $entry['result'];
                $plan = $entry['plan'];
                $profile = $entry['snapshot']['profile'] ?? $entry['snapshot'];
                $rank = $entry['rank'] ?? 0;
                if ($discipline === 'agility') {
                    $awards[] = $this->award($entry, 'AGILITY_START', 'qualification', ['level' => (int) substr($plan['sport_class'] ?? 'agility_1', -1), 'format' => $regulation['format'] ?? 'agility']);
                } elseif ($discipline === 'nosework' && ($regulation['format'] ?? '') !== 'odor_test') {
                    $awards[] = $this->award($entry, 'NW_START', 'qualification', ['level' => $plan['sport_class'], 'program' => $plan['program']]);
                }
                $qualified = ($result['qualified'] ?? ! ($result['eliminated'] ?? true)) && ! ($result['eliminated'] ?? false);
                if ($discipline === 'canicross') {
                    $qualified = $qualified && $raceNorm !== null && $result['time'] <= $raceNorm;
                    if ($qualified) {
                        $awards[] = $this->award($entry, 'RACE_QUALIFICATION', 'qualification', ['race_category' => $profile['race_category'] ?? '4', 'norm_seconds' => $raceNorm, 'time' => $result['time']]);
                    }
                }
                if ($discipline === 'agility' && $qualified && ($result['total_faults'] ?? PHP_INT_MAX) <= $this->balance['regulations']['agility']['excellent_max_faults']) {
                    $awards[] = $this->award($entry, 'AGILITY_QUALIFICATION', 'qualification', ['level' => (int) substr($plan['sport_class'] ?? 'agility_1', -1), 'faults' => $result['total_faults'], 'format' => $regulation['format'] ?? 'agility']);
                }
                if ($discipline === 'nosework') {
                    if (($regulation['format'] ?? '') === 'odor_test') {
                        if ($qualified) {
                            $awards[] = $this->award($entry, 'NW_ODOR_TEST', 'qualification', ['program' => $plan['program'], 'odor' => $plan['test_type']]);
                        }

                        continue;
                    }
                    foreach ($result['standings'] ?? [] as $standing) {
                        if ($standing['key'] !== 'overall' && $standing['qualified'] && $standing['rank'] >= 1 && $standing['rank'] <= 3) {
                            $awards[] = $this->award($entry, 'NW_CATEGORY_'.strtoupper($standing['key']).'_PLACE_'.$standing['rank'], 'placement', [
                                'category' => $standing['key'], 'place' => $standing['rank'], 'class' => $plan['sport_class'], 'program' => $plan['program'],
                            ]);
                        }
                    }
                    if (($result['qualified_categories'] ?? 0) >= $this->balance['regulations']['nosework']['qualification_categories']) {
                        $awards[] = $this->award($entry, 'NW_CLASS_QUALIFICATION', 'qualification', ['level' => $plan['sport_class'], 'program' => $plan['program'], 'qualified_categories' => $result['qualified_categories']]);
                    }
                }
                if (! $qualified) {
                    continue;
                }
                if ($rank >= 1 && $rank <= 3) {
                    $awards[] = $this->award($entry, 'PLACE_'.$rank, 'placement', ['place' => $rank, 'class' => $plan['sport_class'] ?? 'open']);
                }
                $highest = match ($discipline) {
                    'agility' => ($plan['sport_class'] ?? '') === 'agility_3' && (float) ($result['total_faults'] ?? PHP_INT_MAX) === 0.0 && ($regulation['format'] ?? 'agility') === 'agility',
                    'nosework' => ($plan['sport_class'] ?? '') === 'nw_3' && count($result['stages'] ?? []) >= 4,
                    'canicross' => true,
                    default => false,
                };
                if (! $highest) {
                    continue;
                }
                $pedigreed = ($profile['registered'] ?? false) && ($profile['recognized_rkf'] ?? false)
                    && ($discipline !== 'canicross' || ($profile['race_category'] ?? '4') !== '4');
                $eventRank = $regulation['rank'] ?? 'qualifying';
                if (($regulation['system'] ?? 'rkf') === 'fci') {
                    if ($discipline === 'agility' && ($profile['registered'] ?? false) && ($profile['recognized_fci'] ?? false) && $eventRank === 'certificate' && $rank <= 2 && $rank > 0) {
                        $awards[] = $this->award($entry, $rank === 1 ? 'CACIAG' : 'R_CACIAG', 'certificate', $rank === 2 ? ['winner_championships' => ($participants[0]['snapshot']['profile']['championships'] ?? [])] : []);
                    }

                    continue;
                }
                $titleCode = match ($eventRank) {
                    'championship' => 'SPORT_CH_RUS', 'cup' => 'SPORT_CUP_RUS', 'cac_rkf', 'cac_rkf_special' => 'SPORT_CH_RKF', default => null,
                };
                if ($rank === 1 && $titleCode !== null && ($titleCode !== 'SPORT_CH_RKF' || $pedigreed)) {
                    $awards[] = $this->award($entry, $titleCode, 'championship');
                }
                $certificateRank = $eventRank === 'certificate' && $rank === 1;
                $runnerCertificate = $titleCode !== null && $rank === 2;
                if ($pedigreed && ($certificateRank || $runnerCertificate)) {
                    $code = match ($discipline) {
                        'agility' => 'CACAg', 'nosework' => 'CACNW', 'canicross' => 'CACT', default => '',
                    };
                    if ($code !== '') {
                        $awards[] = $this->award($entry, $code, 'certificate');
                    }
                }
            }
        }

        return $awards;
    }

    /** @param list<array<string,mixed>> $entries */
    private function raceNorm(array $entries): ?float
    {
        $finishers = array_values(array_filter($entries, fn (array $entry): bool => ! ($entry['result']['eliminated'] ?? true)));
        usort($finishers, fn (array $first, array $second): int => $first['result']['time'] <=> $second['result']['time']);
        if ($finishers === []) {
            return null;
        }
        $leaders = array_slice($finishers, 0, 3);
        $average = array_sum(array_map(fn (array $entry): float => (float) $entry['result']['time'], $leaders)) / count($leaders);
        if (count($leaders) < 3) {
            return $average;
        }
        $profile = $leaders[0]['snapshot']['profile'] ?? $leaders[0]['snapshot'];

        return $average * ($this->balance['regulations']['canicross']['qualification_factors'][$profile['race_category'] ?? '4'] ?? 1.3);
    }

    /**
     * @param  array<string,mixed>  $entry
     * @param  array<string,mixed>  $details
     * @return array<string,mixed>
     */
    private function award(array $entry, string $code, string $kind, array $details = []): array
    {
        return ['entry_id' => $entry['id'], 'code' => $code, 'kind' => $kind, 'details' => $details];
    }
}
