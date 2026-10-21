<?php

namespace App\Modules\Pets\Calculators;

final class RegionalSportAwards
{
    /** @param array<string,mixed> $balance */
    public function __construct(private RegulatedSportAwards $base, private array $balance) {}

    /**
     * @param  array<string,mixed>  $regulation
     * @param  list<array<string,mixed>>  $entries
     * @return list<array<string,mixed>>
     */
    public function calculate(string $discipline, array $regulation, array $entries): array
    {
        if ((int) ($regulation['version'] ?? 1) !== 2) {
            return $this->base->calculate($discipline, $regulation, $entries);
        }
        $entries = array_values(array_filter($entries, $this->started(...)));
        $awards = array_values(array_filter($this->base->calculate($discipline, $regulation, $entries), fn (array $award): bool => ! in_array($award['kind'], ['certificate', 'championship'], true)));
        if ($discipline === 'canicross') {
            $startersByDivision = array_count_values(array_column($entries, 'division'));
            $entryDivisions = array_column($entries, 'division', 'id');
            $awards = array_values(array_filter($awards, fn (array $award): bool => $award['code'] !== 'RACE_QUALIFICATION'
                || ($startersByDivision[$entryDivisions[$award['entry_id']]] ?? 0) >= 3));
        }
        if ($discipline === 'agility' && ($regulation['format'] ?? '') === 'agility_combined') {
            $awards = array_values(array_filter($awards, fn (array $award): bool => $award['code'] !== 'AGILITY_QUALIFICATION'));
            $agilityEntries = array_map(fn (array $entry): array => [...$entry, 'result' => $entry['result']['legs']['agility'] ?? ['eliminated' => true, 'qualified' => false]], $entries);
            $qualifications = $this->base->calculate('agility', [...$regulation, 'format' => 'agility', 'rank' => 'qualifying'], $agilityEntries);
            foreach ($qualifications as $qualification) {
                if ($qualification['code'] === 'AGILITY_QUALIFICATION') {
                    $awards[] = $qualification;
                }
            }
        }
        $certificate = $this->balance['certificate_codes'][$discipline] ?? null;
        if (! is_string($certificate)) {
            return $awards;
        }
        $breedScope = ($regulation['sportScope'] ?? 'general') === 'breed' && isset($regulation['breedId']);
        $divisions = [];
        foreach ($entries as $entry) {
            if ($breedScope && (int) ($this->profile($entry)['breed_id'] ?? 0) !== (int) $regulation['breedId']) {
                continue;
            }
            $divisions[$entry['division']][] = $entry;
        }
        foreach ($divisions as $participants) {
            usort($participants, fn (array $first, array $second): int => [(int) ($first['rank'] ?? PHP_INT_MAX), (int) $first['id']] <=> [(int) ($second['rank'] ?? PHP_INT_MAX), (int) $second['id']]);
            $norm = $discipline === 'canicross' ? $this->raceNorm($participants) : null;
            $breedWinners = [];
            foreach ($participants as $entry) {
                $rank = (int) ($entry['rank'] ?? 0);
                $profile = $this->profile($entry);
                $breed = (int) ($profile['breed_id'] ?? 0);
                $firstInBreed = $rank > 0 && ! isset($breedWinners[$breed]);
                if ($rank > 0) {
                    $breedWinners[$breed] = true;
                }
                if ($rank < 1 || ! $this->meetsFieldMinimum($discipline, $regulation, $entries, $entry, $breedScope)
                    || ! $this->meetsNorm($discipline, $regulation, $entry, $norm)) {
                    continue;
                }
                $pedigreed = ($profile['registered'] ?? false) && ($profile['recognized_rkf'] ?? false)
                    && ($breedScope || $discipline !== 'canicross' || ($profile['race_category'] ?? '4') !== '4');
                if (($regulation['system'] ?? 'rkf') === 'fci') {
                    if ($discipline === 'agility' && ! $breedScope && ($profile['registered'] ?? false) && ($profile['recognized_fci'] ?? false)
                        && ($regulation['rank'] ?? '') === 'certificate' && $rank <= 2) {
                        $details = $rank === 2 ? ['winner_championships' => $this->profile($participants[0])['championships'] ?? []] : [];
                        $awards[] = $this->award($entry, $rank === 1 ? 'CACIAG' : 'R_CACIAG', 'certificate', $regulation, $discipline, $details);
                    }

                    continue;
                }
                $eventRank = $regulation['rank'] ?? 'qualifying';
                $title = match ($eventRank) {
                    'championship' => 'SPORT_CH_RUS', 'cup' => 'SPORT_CUP_RUS',
                    'cac_rkf', 'cac_rkf_special' => $breedScope ? 'SPORT_BREED_RKF' : 'SPORT_CH_RKF', default => null,
                };
                if ($rank === 1 && $title !== null && ($pedigreed || in_array($title, ['SPORT_CH_RUS', 'SPORT_CUP_RUS'], true))) {
                    $awards[] = $this->award($entry, $title, 'championship', $regulation, $discipline);
                }
                if (! $pedigreed || ($discipline === 'nosework' && $rank !== 1 && ($this->balance['nosework_strict_first_place'] ?? true))) {
                    continue;
                }
                if ($breedScope) {
                    if (($eventRank === 'certificate' && $rank === 1) || ($title === 'SPORT_BREED_RKF' && $rank === 2)) {
                        $awards[] = $this->award($entry, $certificate.'_BREED', 'certificate', $regulation, $discipline);
                    }

                    continue;
                }
                if (($eventRank === 'certificate' && $rank === 1) || ($title !== null && $rank === 2)) {
                    $awards[] = $this->award($entry, $certificate, 'certificate', $regulation, $discipline);
                }
                if ($rank <= 3) {
                    $awards[] = $this->award($entry, 'REG_'.$certificate, 'certificate', $regulation, $discipline);
                }
                if ($firstInBreed && $breed > 0 && in_array($eventRank, ['championship', 'cup'], true)) {
                    $awards[] = $this->award($entry, $certificate.'_BREED', 'certificate', $regulation, $discipline);
                }
            }
        }

        return $awards;
    }

    /** @param array<string,mixed> $entry */
    private function started(array $entry): bool
    {
        return ! in_array($entry['status'] ?? 'completed', ['withdrawn', 'cancelled', 'registered'], true)
            && ($entry['result']['started'] ?? true) !== false;
    }

    /**
     * @param  array<string,mixed>  $regulation
     * @param  list<array<string,mixed>>  $entries
     * @param  array<string,mixed>  $entry
     */
    private function meetsFieldMinimum(string $discipline, array $regulation, array $entries, array $entry, bool $breedScope): bool
    {
        $rank = ($regulation['system'] ?? 'rkf') === 'fci' ? 'international' : ($regulation['rank'] ?? 'qualifying');
        $minimum = $this->balance['minimum_participants'][$breedScope ? 'breed' : 'general'][$discipline][$rank] ?? null;
        if (! is_int($minimum)) {
            return false;
        }
        $profile = $this->profile($entry);
        $count = 0;
        foreach ($entries as $candidate) {
            if (($candidate['plan']['sport_class'] ?? '') !== ($entry['plan']['sport_class'] ?? '')) {
                continue;
            }
            $other = $this->profile($candidate);
            if ($breedScope) {
                if ((int) ($other['breed_id'] ?? 0) !== (int) $regulation['breedId']) {
                    continue;
                }
            } elseif (($discipline === 'agility' && ($other['height_category'] ?? '') !== ($profile['height_category'] ?? ''))
                || ($discipline === 'canicross' && ($other['race_category'] ?? '4') !== ($profile['race_category'] ?? '4'))) {
                continue;
            }
            if ($discipline === 'nosework' && ($candidate['plan']['program'] ?? '') !== ($entry['plan']['program'] ?? '')) {
                continue;
            }
            $count++;
        }

        return $count >= $minimum;
    }

    /**
     * @param  array<string,mixed>  $regulation
     * @param  array<string,mixed>  $entry
     */
    private function meetsNorm(string $discipline, array $regulation, array $entry, ?float $raceNorm): bool
    {
        $result = $entry['result'];
        if (($result['eliminated'] ?? true) || ! ($result['qualified'] ?? true)) {
            return false;
        }
        if ($discipline === 'agility') {
            if (($entry['plan']['sport_class'] ?? '') !== 'agility_3') {
                return false;
            }
            if (($regulation['system'] ?? 'rkf') === 'fci') {
                return ($regulation['format'] ?? '') === 'agility' && (float) ($result['total_faults'] ?? PHP_INT_MAX) === 0.0;
            }
            if (($regulation['format'] ?? '') !== 'agility_combined') {
                return false;
            }
            foreach (['agility', 'jumping'] as $leg) {
                if (($result['legs'][$leg]['eliminated'] ?? true) || (float) ($result['legs'][$leg]['total_faults'] ?? PHP_INT_MAX) !== 0.0) {
                    return false;
                }
            }

            return (float) ($result['total_faults'] ?? PHP_INT_MAX) === 0.0;
        }
        if ($discipline === 'nosework') {
            if (($entry['plan']['sport_class'] ?? '') !== 'nw_3' || count($result['stages'] ?? []) < 4 || ($result['qualified_categories'] ?? 0) < count($result['stages'])) {
                return false;
            }
            foreach ($result['stages'] as $stage) {
                if (($stage['score'] ?? 0) < ($this->balance['nosework_minimum_category_score'] ?? 80) || ! ($stage['qualified'] ?? true) || ($stage['eliminated'] ?? false)) {
                    return false;
                }
            }

            return true;
        }

        return $discipline === 'canicross' && $raceNorm !== null && (float) ($result['time'] ?? PHP_INT_MAX) <= $raceNorm;
    }

    /** @param list<array<string,mixed>> $entries */
    private function raceNorm(array $entries): ?float
    {
        $finishers = array_values(array_filter($entries, fn (array $entry): bool => ! ($entry['result']['eliminated'] ?? true)));
        usort($finishers, fn (array $first, array $second): int => $first['result']['time'] <=> $second['result']['time']);
        $leaders = array_slice($finishers, 0, 3);
        if ($leaders === []) {
            return null;
        }
        $average = array_sum(array_map(fn (array $entry): float => (float) $entry['result']['time'], $leaders)) / count($leaders);
        $category = $this->profile($leaders[0])['race_category'] ?? '4';

        return count($leaders) < 3 ? $average : $average * ($this->balance['race_qualification_factors'][$category] ?? 1.3);
    }

    /**
     * @param  array<string,mixed>  $entry
     * @return array<string,mixed>
     */
    private function profile(array $entry): array
    {
        return $entry['snapshot']['profile'] ?? $entry['snapshot'];
    }

    /**
     * @param  array<string,mixed>  $entry
     * @param  array<string,mixed>  $regulation
     * @param  array<string,mixed>  $details
     * @return array<string,mixed>
     */
    private function award(array $entry, string $code, string $kind, array $regulation, string $discipline, array $details = []): array
    {
        $sources = ['rkf_sport_titles', 'rkf_sport_events', match ($discipline) {
            'agility' => 'rkf_agility_2026',
            'nosework' => 'rkf_nosework_2026_09', default => 'rkf_racing_2026_06',
        }];
        if (($regulation['system'] ?? 'rkf') === 'fci') {
            $sources = ['fci_agility', 'fci_agility_champion'];
        }

        return ['entry_id' => $entry['id'], 'code' => $code, 'kind' => $kind, 'scope_key' => '', 'details' => [...$details,
            'policy_version' => 2, 'source_keys' => $sources, 'sport_scope' => $regulation['sportScope'] ?? 'general',
            'region' => $regulation['region'] ?? '', 'breed_id' => $this->profile($entry)['breed_id'] ?? null,
            'nw_strict_first_place' => $discipline === 'nosework' && ($this->balance['nosework_strict_first_place'] ?? true)]];
    }
}
