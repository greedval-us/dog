<?php

namespace App\Modules\Pets\Actions;

use App\Models\Puppy;
use App\Models\PuppyPlacement;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Pets\Services\PuppyLifecycle;
use App\Modules\Pets\Services\PuppyPlacementService;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class KeepPuppy
{
    public function __construct(private PuppyLifecycle $puppies, private PetLifecycle $lifecycle, private PuppyPlacementService $placements) {}

    public function handle(User $user, int $puppyId, string $name, string $token): PuppyPlacement
    {
        $name = trim($name);
        $token = strtolower($token);
        if (! Str::isUuid($token) || $name === '' || mb_strlen($name) > 64 || $puppyId < 1) {
            throw new PetUnavailable('Invalid puppy placement.');
        }
        $this->puppies->synchronize($puppyId);
        $this->lifecycle->synchronizeOwner($user);

        try {
            return DB::transaction(function () use ($user, $puppyId, $name, $token): PuppyPlacement {
                $owner = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($owner->status !== PlayerStatus::Active) {
                    throw new PetUnavailable('Your account is blocked.');
                }
                $existing = $this->placements->replay($owner, $puppyId, $name, $token, 'keep', 0);
                if ($existing !== null) {
                    return $existing;
                }
                $puppy = Puppy::query()->lockForUpdate()->findOrFail($puppyId);
                if ($puppy->user_id !== $owner->id || ! in_array($puppy->status, ['pending', 'listed'], true)
                    || $puppy->expires_at->lessThanOrEqualTo(now())) {
                    throw new PetUnavailable('This puppy is no longer available to keep.');
                }

                return $this->placements->place($owner, $puppy, $name, $token, 'keep', 0);
            }, attempts: 3);
        } catch (PetUnavailable $exception) {
            $this->puppies->synchronize($puppyId);
            throw $exception;
        }
    }
}
