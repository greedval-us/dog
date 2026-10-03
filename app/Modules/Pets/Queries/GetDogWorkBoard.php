<?php

namespace App\Modules\Pets\Queries;

use App\Models\DogWorkBoard;
use App\Models\DogWorkShift;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Calculators\DogWorkRules;
use App\Modules\Pets\Calculators\PetDecayCalculator;
use App\Modules\Pets\Calculators\SkillRules;
use App\Modules\Players\Enums\PlayerStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class GetDogWorkBoard
{
    public function __construct(private SkillRules $skills, private DogWorkRules $rules, private PetDecayCalculator $decay,
        private GetPetStatSnapshot $getAttributes) {}

    /** @return array<string, mixed> */
    public function handle(User $user, DogWorkBoard $board, ?int $petId, string $locale): array
    {
        $pets = $user->pets()->active()->orderBy('id')->get();
        $pet = $petId === null ? $pets->first() : $pets->firstWhere('id', $petId);
        if ($petId !== null && $pet === null) {
            $user->pets()->active()->whereKey($petId)->firstOrFail();
        }
        $pet?->load('skills');
        $at = now();
        $pet?->advanceTo($at, $this->decay);
        $offers = $board->offers()->orderBy('id')->get();
        $participation = DogWorkShift::query()->where('user_id', $user->id)
            ->whereIn('dog_work_offer_id', $offers->modelKeys())->get()->keyBy('dog_work_offer_id');
        $attributes = $pet === null ? null : $this->getAttributes->handle($pet);

        $jobs = [];
        foreach ($offers as $offer) {
            $skill = $pet?->skills->firstWhere('id', $offer->required_skill_id);
            $level = $skill?->pivot->level ?? 0;
            $hasRequiredLevel = $skill !== null
                && $this->skills->hasLearnedLevel($skill->levels, $skill->is_active, $level, $offer->required_skill_level);
            $active = $pet !== null && $attributes !== null && $skill !== null
                && $this->skills->isActive($attributes, $skill->levels, $skill->is_active, $level, ! $pet->isActive());
            $shift = $participation->get($offer->id);
            $places = max(0, $offer->daily_limit - $offer->reserved_count);
            $reason = match (true) {
                $shift !== null => 'You have already taken this job today.',
                $places === 0 => 'All places for this job have been taken.',
                $user->status !== PlayerStatus::Active => 'Your account is blocked.',
                $pet === null => 'Choose a dog to take a job.',
                $pet->retired_at !== null => 'Retired dogs cannot work.',
                ! $pet->isActive() => 'This dog is no longer active.',
                $pet->isBusy() => 'Finish your dog’s current activity before starting work.',
                ! $this->rules->valid($offer->required_skill_level, $offer->coins_reward, $offer->gems_reward,
                    $offer->duration_seconds, $offer->energy_cost, $offer->daily_limit) => 'This job is no longer available. Refresh the board.',
                ! $hasRequiredLevel => 'Your dog needs the required skill level.',
                ! $active => 'Raise your dog’s attributes to reactivate this skill.',
                $pet->energy < $offer->energy_cost => 'Your dog does not have enough energy for this job.',
                default => null,
            };
            $jobs[] = [
                'id' => $offer->id, 'name' => $offer->name[$locale] ?? $offer->name['en'] ?? $offer->code,
                'description' => $offer->description[$locale] ?? $offer->description['en'] ?? '',
                'skill' => $offer->required_skill_name[$locale] ?? $offer->required_skill_name['en'] ?? '',
                'skillLevel' => $offer->required_skill_level, 'dogSkillLevel' => $level, 'skillActive' => $active,
                'coins' => $offer->coins_reward, 'gems' => $offer->gems_reward,
                'duration' => $offer->duration_seconds, 'energy' => $offer->energy_cost,
                'places' => $places, 'limit' => $offer->daily_limit, 'reason' => $reason,
                'status' => $shift === null ? null : ($shift->cancelled_at !== null ? 'cancelled' : ($shift->completed_at === null ? 'started' : 'completed')),
            ];
        }

        return [
            'token' => (string) Str::uuid(), 'serverNow' => $at->toIso8601String(), 'date' => $board->work_date->toDateString(),
            'resetsAt' => CarbonImmutable::parse($board->work_date->toDateString(), config('doglive.work_timezone'))->addDay()->toIso8601String(),
            'timezone' => config('doglive.work_timezone'), 'selectedPetId' => $pet?->id,
            'dogs' => $pets->map(fn (Pet $dog): array => ['id' => $dog->id, 'name' => $dog->name,
                'busy' => $dog->isBusy(), 'retired' => $dog->retired_at !== null])->all(),
            'offers' => $jobs,
            'shifts' => DogWorkShift::query()->where('user_id', $user->id)->whereNull('completed_at')->whereNull('cancelled_at')->orderBy('ends_at')->get()
                ->map(fn (DogWorkShift $shift): array => [
                    'token' => $shift->token, 'petId' => $shift->pet_id, 'petName' => $shift->pet_name,
                    'name' => $shift->name[$locale] ?? $shift->name['en'] ?? '',
                    'endsAt' => $shift->ends_at->toIso8601String(), 'coins' => $shift->coins_reward, 'gems' => $shift->gems_reward,
                ])->all(),
        ];
    }
}
