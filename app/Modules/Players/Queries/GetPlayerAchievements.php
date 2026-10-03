<?php

namespace App\Modules\Players\Queries;

use App\Models\Achievement;
use App\Models\PlayerAchievement;
use App\Models\User;

final class GetPlayerAchievements
{
    /** @return list<array{id: int, code: string, name: string, description: string, imageUrl: string, rule: string, progress: int, target: int, unlockedAt: string|null}> */
    public function handle(User $user, string $locale): array
    {
        $states = PlayerAchievement::query()->where('user_id', $user->id)->get()->keyBy('achievement_id');

        return array_values(Achievement::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (Achievement $achievement) use ($states, $locale): array {
                $state = $states->get($achievement->id);

                return [
                    'id' => $achievement->id,
                    'code' => $achievement->code,
                    'name' => $achievement->name[$locale] ?? $achievement->name['ru'] ?? $achievement->code,
                    'description' => $achievement->description[$locale] ?? $achievement->description['ru'] ?? '',
                    'imageUrl' => asset($achievement->image_path),
                    'rule' => $achievement->rule_description[$locale] ?? $achievement->rule_description['ru'] ?? '',
                    'progress' => min($achievement->rules['target'], $state === null ? 0 : $state->progress),
                    'target' => $achievement->rules['target'],
                    'unlockedAt' => $state?->unlocked_at?->toISOString(),
                ];
            })->all());
    }
}
