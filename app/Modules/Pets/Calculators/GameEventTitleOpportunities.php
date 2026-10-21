<?php

namespace App\Modules\Pets\Calculators;

final class GameEventTitleOpportunities
{
    private const PODIUM_SIZE = 3;

    /** @param array<string,mixed> $minimumParticipants */
    public function __construct(
        private RegulatedExhibitionAwards $shows,
        private SpecialExhibitionAwards $specialShows,
        private RegulatedSportAwards $sports,
        private RegionalSportAwards $regionalSports,
        private RegulatedEventRules $rules,
        private array $minimumParticipants,
    ) {}

    /**
     * A conditional forecast uses the award calculator's real policies with a successful result.
     * It does not predict the dog's performance or the number of actual starters.
     *
     * @param  array<string,mixed>  $regulation
     * @param  array<string,mixed>  $profile
     * @param  array<string,mixed>  $plan
     * @return array<string,array{rank:int,minimumParticipants:int}>
     */
    public function codes(string $discipline, array $regulation, array $profile, array $plan): array
    {
        $scope = ($regulation['sportScope'] ?? 'general') === 'breed' && isset($regulation['breedId']) ? 'breed' : 'general';
        $rank = ($regulation['system'] ?? 'rkf') === 'fci' ? 'international' : ($regulation['rank'] ?? 'qualifying');
        $expanded = (int) ($regulation['version'] ?? 1) >= 2;
        $minimum = $expanded ? ($this->minimumParticipants[$scope][$discipline][$rank] ?? 0) : 0;
        $fieldSize = max(self::PODIUM_SIZE, $minimum);
        $codes = [];
        foreach (range(1, self::PODIUM_SIZE) as $place) {
            $entries = $this->field($discipline, $regulation, $profile, $plan, $place, $fieldSize);
            $awards = $discipline === 'conformation'
                ? ($expanded ? $this->specialShows : $this->shows)->calculate($regulation, $entries)
                : ($expanded ? $this->regionalSports : $this->sports)->calculate($discipline, $regulation, $entries);
            foreach ($awards as $award) {
                if ($award['entry_id'] === 1) {
                    $codes[$award['code']] = ['rank' => $place, 'minimumParticipants' => $minimum];
                }
            }
        }

        return $codes;
    }

    /**
     * @param  array<string,mixed>  $regulation
     * @param  array<string,mixed>  $profile
     * @param  array<string,mixed>  $plan
     * @return list<array<string,mixed>>
     */
    private function field(string $discipline, array $regulation, array $profile, array $plan, int $place, int $size): array
    {
        $entries = [];
        $young = in_array($plan['exhibition_class'] ?? '', ['baby', 'puppy'], true);
        foreach (range(1, $size) as $id) {
            $rank = $id === 1 ? $place : ($id <= $place ? $id - 1 : $id);
            $stages = array_map(fn (string $key): array => ['key' => $key, 'score' => 100, 'qualified' => true, 'eliminated' => false], $this->rules->stages($discipline, $regulation, $plan));
            $entries[] = [
                'id' => $id, 'division' => $this->rules->division($discipline, $regulation, $profile, $plan),
                'status' => 'completed', 'rank' => $rank, 'plan' => $plan,
                'snapshot' => ['profile' => $profile, 'sex' => $profile['sex'] ?? 'male'],
                'result' => [
                    'score' => 100 - $rank, 'time' => 100 + $rank, 'total_faults' => 0,
                    'eliminated' => false, 'qualified' => true, 'grade' => $young ? 'very_promising' : 'excellent',
                    'found_all' => true, 'qualified_categories' => count($stages), 'stages' => $stages,
                    'legs' => ['agility' => ['eliminated' => false, 'qualified' => true, 'total_faults' => 0],
                        'jumping' => ['eliminated' => false, 'qualified' => true, 'total_faults' => 0]],
                ],
            ];
        }

        return $entries;
    }
}
