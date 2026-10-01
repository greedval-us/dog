<?php

namespace App\Modules\Pets\Actions;

use App\Models\DogWorkBoard;
use App\Models\DogWorkType;
use App\Modules\Pets\Calculators\DogWorkRules;
use App\Modules\Pets\Calculators\SkillRules;
use Illuminate\Support\Facades\DB;

final class GenerateDogWorkBoard
{
    public function __construct(private DogWorkRules $rules, private SkillRules $skills) {}

    public function handle(): DogWorkBoard
    {
        $date = now(config('doglive.work_timezone'))->toDateString();
        $existing = DogWorkBoard::query()->where('work_date', $date)->first();
        if ($existing?->generated_at !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($date): DogWorkBoard {
            DogWorkBoard::query()->insertOrIgnore(['work_date' => $date, 'created_at' => now(), 'updated_at' => now()]);
            $board = DogWorkBoard::query()->where('work_date', $date)->lockForUpdate()->firstOrFail();
            if ($board->generated_at !== null) {
                return $board;
            }

            $remaining = max(1, min(100, (int) config('doglive.dog_work_daily_offers', 6)));
            $jobs = DogWorkType::query()->with('skill')->where('is_active', true)
                ->whereHas('skill', fn ($query) => $query->where('is_active', true))->inRandomOrder()->get();

            foreach ($jobs as $job) {
                if (! $this->rules->valid($job->required_skill_level, $job->coins_reward, $job->gems_reward,
                    $job->duration_seconds, $job->energy_cost, $job->daily_limit) || ! $this->skills->valid($job->skill->levels)) {
                    continue;
                }
                $board->offers()->create([
                    'dog_work_type_id' => $job->id, 'code' => $job->code, 'name' => $job->name, 'description' => $job->description,
                    'required_skill_id' => $job->required_skill_id, 'required_skill_name' => $job->skill->name,
                    'required_skill_level' => $job->required_skill_level, 'coins_reward' => $job->coins_reward,
                    'gems_reward' => $job->gems_reward, 'duration_seconds' => $job->duration_seconds,
                    'energy_cost' => $job->energy_cost, 'daily_limit' => $job->daily_limit,
                ]);
                if (--$remaining === 0) {
                    break;
                }
            }

            $board->update(['generated_at' => now()]);

            return $board;
        }, attempts: 3);
    }
}
