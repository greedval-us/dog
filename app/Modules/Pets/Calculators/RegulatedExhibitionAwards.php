<?php

namespace App\Modules\Pets\Calculators;

final class RegulatedExhibitionAwards
{
    /** @param array<string,mixed> $balance */
    public function __construct(private array $balance) {}

    /**
     * @param  array<string,mixed>  $regulation
     * @param  list<array<string,mixed>>  $entries
     * @return list<array<string,mixed>>
     */
    public function calculate(array $regulation, array $entries): array
    {
        $awards = [];
        $rings = [];
        foreach ($entries as $entry) {
            if (! ($entry['result']['eliminated'] ?? false) && in_array($entry['result']['grade'] ?? '', ['excellent', 'very_good', 'good'], true)) {
                $awards[] = $this->award($entry, 'SHOW_GRADE', 'qualification', ['grade' => $entry['result']['grade'], 'age_months' => $entry['snapshot']['age_months'] ?? null]);
            }
            if (($entry['result']['eliminated'] ?? false) || ! ($entry['result']['qualified'] ?? false)) {
                continue;
            }
            $profile = $this->profile($entry);
            $class = $entry['plan']['exhibition_class'] ?? 'progeny';
            $rings[($profile['breed_id'] ?? 0).':'.($profile['sex'] ?? 'male').':'.$class][] = $entry;
        }
        $winners = [];
        $runners = [];
        foreach ($rings as $ring => $participants) {
            $participants = $this->sorted($participants);
            foreach (array_slice($participants, 0, $this->balance['regulations']['shows']['placements']) as $index => $entry) {
                $awards[] = $this->award($entry, 'PLACE_'.($index + 1), 'placement', ['place' => $index + 1, 'class' => $entry['plan']['exhibition_class'] ?? 'progeny']);
            }
            $first = $participants[0];
            if (in_array($first['result']['grade'] ?? '', ['excellent', 'very_promising'], true)) {
                $winners[$ring] = $first;
                $awards[] = $this->award($first, 'CW', 'comparison');
                if (isset($participants[1]) && ($participants[1]['result']['grade'] ?? '') === 'excellent') {
                    $runners[$ring] = $participants[1];
                }
            }
        }
        $breeds = [];
        foreach ($winners as $ring => $entry) {
            $profile = $this->profile($entry);
            $breeds[(string) ($profile['breed_id'] ?? 0)][$profile['sex'] ?? 'male'][$entry['plan']['exhibition_class'] ?? 'progeny'] = ['entry' => $entry, 'runner' => $runners[$ring] ?? null];
        }
        $bestOfBreeds = [];
        $rank = $regulation['rank'] ?? 'qualifying';
        $certificateShow = in_array($rank, ['cac_cf', 'cac_rkf', 'cac_rkf_special', 'cacib', 'specialty'], true);
        $nationalShow = $certificateShow && ($regulation['country'] ?? 'RU') === 'RU';
        foreach ($breeds as $sexes) {
            $breedCandidates = [];
            $young = [];
            foreach ($sexes as $sex => $classes) {
                $adult = [];
                foreach (['intermediate', 'open', 'working', 'champion', 'club_champion'] as $class) {
                    if (isset($classes[$class])) {
                        $adult[] = $classes[$class]['entry'];
                    }
                }
                $adult = $this->sorted($adult);
                if ($adult !== []) {
                    $best = $adult[0];
                    $breedCandidates[] = $best;
                    $awards[] = $this->award($best, $sex === 'male' ? 'BEST_MALE' : 'BEST_FEMALE', 'comparison');
                    if ($certificateShow) {
                        $perClass = in_array($rank, ['cacib', 'cac_rkf_special'], true);
                        $certificate = $rank === 'specialty' ? 'KCHK' : 'CAC';
                        $certificateAdults = array_values(array_filter($adult, fn (array $entry): bool => ($entry['plan']['exhibition_class'] ?? '') !== 'club_champion'));
                        $certificateWinners = $perClass ? $certificateAdults : array_slice($certificateAdults, 0, 1);
                        foreach ($nationalShow ? $certificateWinners : [] as $winner) {
                            if ($this->certificateEligible($winner, $regulation)) {
                                $awards[] = $this->award($winner, $certificate, 'certificate');
                                $class = $winner['plan']['exhibition_class'];
                                $reserveCandidates = $perClass ? [] : array_slice($certificateAdults, 1);
                                if (isset($classes[$class]['runner'])) {
                                    $reserveCandidates[] = $classes[$class]['runner'];
                                }
                                $reserve = $this->sorted($reserveCandidates)[0] ?? null;
                                if ($reserve !== null && $this->certificateEligible($reserve, $regulation)) {
                                    $awards[] = $this->award($reserve, 'R_'.$certificate, 'certificate', ['winner_championships' => $this->profile($winner)['championships'] ?? []]);
                                }
                            }
                        }
                        if ($nationalShow && in_array($rank, ['cac_rkf', 'cac_rkf_special', 'cacib'], true) && $this->certificateEligible($best, $regulation)) {
                            $awards[] = $this->award($best, 'CH_RKF_CERT', 'certificate');
                        }
                        if ($rank === 'cacib' && $this->internationalEligible($best)) {
                            $awards[] = $this->award($best, 'CACIB', 'certificate');
                            $class = $best['plan']['exhibition_class'];
                            $candidates = array_slice($adult, 1);
                            if (isset($classes[$class]['runner'])) {
                                $candidates[] = $classes[$class]['runner'];
                            }
                            $reserve = $this->sorted($candidates)[0] ?? null;
                            if ($reserve !== null && $this->internationalEligible($reserve)) {
                                $awards[] = $this->award($reserve, 'R_CACIB', 'certificate', ['winner_championships' => $this->profile($best)['championships'] ?? []]);
                            }
                        }
                    }
                }
                foreach (['baby', 'puppy', 'junior', 'veteran'] as $class) {
                    if (! isset($classes[$class])) {
                        continue;
                    }
                    $entry = $classes[$class]['entry'];
                    $young[$class][] = $entry;
                    if (in_array($class, ['junior', 'veteran'], true)) {
                        $breedCandidates[] = $entry;
                        if ($certificateShow && $this->certificateEligible($entry, $regulation)) {
                            if ($nationalShow) {
                                $code = $rank === 'specialty' ? ($class === 'junior' ? 'JKCHK' : 'VKCHK') : ($class === 'junior' ? 'JCAC' : 'VCAC');
                                $awards[] = $this->award($entry, $code, 'certificate');
                                $reserve = $classes[$class]['runner'] ?? null;
                                if ($reserve !== null && $this->certificateEligible($reserve, $regulation)) {
                                    $awards[] = $this->award($reserve, 'R_'.$code, 'certificate', ['winner_championships' => $this->profile($entry)['championships'] ?? []]);
                                }
                            }
                            if ($nationalShow && in_array($rank, ['cac_rkf', 'cac_rkf_special', 'cacib'], true)) {
                                $awards[] = $this->award($entry, $class === 'junior' ? 'JCH_RKF_CERT' : 'VCH_RKF_CERT', 'certificate');
                            }
                            if ($rank === 'cacib' && $this->internationalEligible($entry)) {
                                $awards[] = $this->award($entry, $class === 'junior' ? 'CACIB_J' : 'CACIB_V', 'certificate');
                            }
                        }
                    }
                }
            }
            foreach ($young as $class => $candidates) {
                $awards[] = $this->award($this->sorted($candidates)[0], 'BEST_'.strtoupper($class), 'comparison');
            }
            $candidates = $this->sorted($breedCandidates);
            if ($candidates !== []) {
                $bob = $candidates[0];
                $bestOfBreeds[] = $bob;
                $awards[] = $this->award($bob, 'BOB', 'comparison');
                $opposite = array_values(array_filter($candidates, fn (array $entry): bool => ($this->profile($entry)['sex'] ?? '') !== ($this->profile($bob)['sex'] ?? '')));
                if ($opposite !== []) {
                    $awards[] = $this->award($opposite[0], 'BOS', 'comparison');
                }
            }
        }
        if ($rank !== 'specialty') {
            $groups = [];
            foreach ($bestOfBreeds as $entry) {
                $group = $this->profile($entry)['fci_group'] ?? null;
                if (is_int($group) && $group >= 1 && $group <= 10) {
                    $groups[$group][] = $entry;
                }
            }
            $finalists = [];
            foreach ($groups as $group => $candidates) {
                $ordered = $this->sorted($candidates);
                foreach (array_slice($ordered, 0, $this->balance['regulations']['shows']['final_placements']) as $index => $entry) {
                    $awards[] = $this->award($entry, 'BIG_'.($index + 1), 'comparison', ['place' => $index + 1, 'group' => $group]);
                }
                $finalists[] = $ordered[0];
            }
            foreach (array_slice($this->sorted($finalists), 0, $this->balance['regulations']['shows']['final_placements']) as $index => $entry) {
                $awards[] = $this->award($entry, 'BIS_'.($index + 1), 'comparison', ['place' => $index + 1]);
            }
        }

        return $awards;
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
     * @param  list<array<string,mixed>>  $entries
     * @return list<array<string,mixed>>
     */
    private function sorted(array $entries): array
    {
        usort($entries, fn (array $first, array $second): int => ($second['result']['score'] <=> $first['result']['score']) ?: (($first['rank'] ?? PHP_INT_MAX) <=> ($second['rank'] ?? PHP_INT_MAX)) ?: ($first['id'] <=> $second['id']));

        return $entries;
    }

    /**
     * @param  array<string,mixed>  $entry
     * @param  array<string,mixed>  $regulation
     */
    private function certificateEligible(array $entry, array $regulation): bool
    {
        $profile = $this->profile($entry);

        return ($profile['registered'] ?? false) && ($profile[($regulation['system'] ?? 'rkf') === 'fci' ? 'recognized_fci' : 'recognized_rkf'] ?? false);
    }

    /** @param array<string,mixed> $entry */
    private function internationalEligible(array $entry): bool
    {
        $profile = $this->profile($entry);

        return ($profile['recognized_fci'] ?? false) && ($profile['registered'] ?? false) && ($profile['pedigree_complete'] ?? false);
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
