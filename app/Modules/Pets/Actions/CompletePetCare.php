<?php

namespace App\Modules\Pets\Actions;

use App\Models\PetCareAction;
use App\Models\StatusEffect;
use App\Models\User;
use App\Modules\Pets\Calculators\PetStatusRules;
use App\Modules\Pets\Enums\PetState;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PetActivityManager;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;

final class CompletePetCare
{
    public function __construct(private PetActivityManager $activities, private PetStatusRules $statuses) {}

    public function handle(User $user, int $petId, string $token): bool
    {
        return DB::transaction(function () use ($user, $petId, $token): bool {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($owner->status !== PlayerStatus::Active) {
                throw new PetUnavailable('This player cannot care for pets.');
            }

            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            $care = PetCareAction::query()->where('user_id', $owner->id)->where('pet_id', $petId)->where('token', strtolower($token))->lockForUpdate()->firstOrFail();

            if ($care->completed_at !== null) {
                return false;
            }

            if (! $this->activities->complete($owner, $petId, $care->activity_token)) {
                throw new PetUnavailable('This activity is not ready to finish.');
            }

            foreach ($care->effects as $name => $percentage) {
                $state = PetState::from($name);
                $maximum = $pet->getAttribute($state->maximumColumn());
                $value = $pet->getAttribute($name) + $maximum * $percentage / 100;
                $pet->setAttribute($name, round(max(0, min($maximum, $value)), 4));
            }

            $pet->buffs = $this->statuses->award($pet->buffs ?? [], $care->granted_effects ?? [], $care->ends_at->getTimestamp(), now()->getTimestamp());
            $catalogue = array_values(StatusEffect::query()->where('is_active', true)->get()->map(fn (StatusEffect $effect): array => $effect->snapshot())->all());
            $pet->debuffs = $this->statuses->debuffs($catalogue, $pet->statePercentages());
            $pet->state_updated_at = now();
            $pet->save();
            $care->completed_at = now();
            $care->save();

            return true;
        }, attempts: 3);
    }
}
