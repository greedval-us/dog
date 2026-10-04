<?php

namespace App\Modules\Pets\Actions;

use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Enums\CareRefusal;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PetCareCompletion;
use App\Modules\Pets\Services\PetLifecycleSynchronization;
use App\Modules\Pets\Services\PetStateSynchronizer;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;

final class CompletePetCare
{
    public function __construct(
        private PetCareCompletion $completion,
        private PetStateSynchronizer $state,
        private PetLifecycleSynchronization $lifecycle,
    ) {}

    public function handle(User $user, int $petId, string $token): bool
    {
        $thresholds = [];
        $completedCareIds = $this->lifecycle->synchronizeOwner($user);

        return DB::transaction(function () use ($user, $petId, $token, $completedCareIds, &$thresholds): bool {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->status !== PlayerStatus::Active) {
                throw PetUnavailable::forCare(CareRefusal::PlayerBlocked);
            }
            $pet = $owner->pets()->lockForUpdate()->findOrFail($petId);
            $care = PetCareAction::query()->where('user_id', $owner->id)->where('pet_id', $petId)
                ->where('token', strtolower($token))->lockForUpdate()->firstOrFail();
            if ($care->completed_at !== null || $care->cancelled_at !== null) {
                return in_array($care->id, $completedCareIds, true);
            }
            $at = now();
            $this->lifecycle->assertCanAdvance($owner, $at);
            if (! $pet->isActive()) {
                $this->lifecycle->persist($pet);

                return false;
            }

            $completed = $this->completion->complete($owner, $pet, $care, $at, $thresholds);
            $this->state->advance($pet, $at);
            $this->lifecycle->persist($pet);

            return $completed;
        }, attempts: 3);
    }
}
