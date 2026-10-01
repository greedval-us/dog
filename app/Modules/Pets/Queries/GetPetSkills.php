<?php

namespace App\Modules\Pets\Queries;

use App\Models\Skill;
use App\Models\User;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\SkillRules;
use App\Modules\Pets\Enums\PetStat;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Str;

/**
 * @phpstan-type SkillLevelView array{price: int, requirements: array<string, int>, requirementPercentages: array<string, int>}
 * @phpstan-type SkillView array{id: int, code: string, name: string, description: string, level: int, active: bool, activeRequirements: array<string, int>, activeRequirementPercentages: array<string, int>, levels: list<SkillLevelView>, cooldownUntil: string|null, canTrain: bool, reason: string|null}
 */
final class GetPetSkills
{
    public function __construct(private SkillRules $rules, private PetDecayCalculator $decay) {}

    /** @return array{serverNow: string, token: string, skills: list<SkillView>} */
    public function handle(User $user, int $petId, string $locale): array
    {
        $pet = $user->pets()->with('skills')->findOrFail($petId);
        $at = now()->startOfSecond();
        $pet->advanceTo($at, $this->decay);
        $stats = [];
        $potentials = [];
        foreach (PetStat::cases() as $stat) {
            $stats[$stat->value] = (int) $pet->getAttribute($stat->value);
            $potentials[$stat->value] = (int) $pet->getAttribute($stat->potentialColumn());
        }

        $learned = $pet->skills->keyBy('id');
        $skills = [];
        foreach (Skill::query()->orderBy('id')->get() as $skill) {
            $progress = $learned->get($skill->id)?->pivot;
            $level = $progress->level ?? 0;
            $valid = $this->rules->valid($skill->levels);
            if ((! $skill->is_active || ! $valid) && $level === 0) {
                continue;
            }
            $levels = [];
            foreach ($valid ? $skill->levels : [] as $lesson) {
                $levels[] = [
                    'price' => $lesson['price'],
                    'requirements' => $this->rules->requiredValues($potentials, $lesson['requirements']),
                    'requirementPercentages' => $lesson['requirements'],
                ];
            }
            $currentRequirements = $levels[$level - 1]['requirements'] ?? [];
            $currentPercentages = $levels[$level - 1]['requirementPercentages'] ?? [];
            $next = $levels[$level] ?? null;
            $active = $level > 0 && $currentRequirements !== [] && $skill->is_active && $pet->retired_at === null
                && $this->rules->meets($stats, $potentials, $currentPercentages);
            $reason = match (true) {
                $user->status !== PlayerStatus::Active => 'Your account is blocked.',
                $pet->retired_at !== null => 'Retired dogs cannot learn skills.',
                ! $skill->is_active || ! $valid => 'This skill is unavailable.',
                $level >= SkillRules::MAX_LEVEL => 'Your dog has mastered all five levels of this skill.',
                $pet->isBusy() => 'Finish your dog’s current activity before a skill lesson.',
                $progress?->cooldown_until?->greaterThan($at) === true => 'Wait 24 hours between lessons for the same skill.',
                $next !== null && ! $this->rules->meets($stats, $potentials, $next['requirementPercentages']) => 'Raise your dog’s attributes to the lesson requirements.',
                $next !== null && $user->coins < $next['price'] => 'You do not have enough coins to pay the instructor.',
                default => null,
            };
            $skills[] = [
                'id' => $skill->id, 'code' => $skill->code,
                'name' => $skill->name[$locale] ?? $skill->name['en'] ?? $skill->code,
                'description' => $skill->description[$locale] ?? $skill->description['en'] ?? '',
                'level' => $level, 'active' => $active, 'activeRequirements' => $currentRequirements,
                'activeRequirementPercentages' => $currentPercentages,
                'levels' => $levels, 'cooldownUntil' => $progress?->cooldown_until?->toIso8601String(),
                'canTrain' => $reason === null && $next !== null, 'reason' => $reason,
            ];
        }

        return ['serverNow' => $at->toIso8601String(), 'token' => (string) Str::uuid(), 'skills' => $skills];
    }
}
