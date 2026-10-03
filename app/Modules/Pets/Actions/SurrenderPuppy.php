<?php

namespace App\Modules\Pets\Actions;

use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PuppyLifecycle;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;

final class SurrenderPuppy
{
    public function __construct(private PuppyLifecycle $lifecycle) {}

    public function handle(User $user, int $puppyId): void
    {
        $this->lifecycle->synchronize($puppyId);
        try {
            DB::transaction(function () use ($user, $puppyId): void {
                $owner = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($owner->status !== PlayerStatus::Active) {
                    throw new PetUnavailable('Your account is blocked.');
                }
                $puppy = Puppy::query()->lockForUpdate()->findOrFail($puppyId);
                if ($puppy->user_id !== $owner->id || ! in_array($puppy->status, ['pending', 'listed'], true)
                    || $puppy->expires_at->lessThanOrEqualTo(now())) {
                    throw new PetUnavailable('This puppy is no longer available to surrender.');
                }
                $puppy->forceFill(['status' => 'kennel', 'user_id' => null, 'sale_price' => null])->save();
            }, attempts: 3);
        } catch (PetUnavailable $exception) {
            $this->lifecycle->synchronize($puppyId);
            throw $exception;
        }
    }
}
