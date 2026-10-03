<?php

namespace App\Modules\Pets\Actions;

use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Services\PuppyLifecycle;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Facades\DB;

final class ListPuppyForSale
{
    public function __construct(private PuppyLifecycle $lifecycle) {}

    public function handle(User $user, int $puppyId, ?int $price): void
    {
        if ($price !== null && ($price < 1 || $price > 1000000)) {
            throw new PetUnavailable('Choose a puppy price between 1 and 1000000 coins.');
        }
        $this->lifecycle->synchronize($puppyId);
        try {
            DB::transaction(function () use ($user, $puppyId, $price): void {
                $owner = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($owner->status !== PlayerStatus::Active) {
                    throw new PetUnavailable('Your account is blocked.');
                }
                $puppy = Puppy::query()->lockForUpdate()->findOrFail($puppyId);
                if ($puppy->user_id !== $owner->id || ! in_array($puppy->status, ['pending', 'listed'], true)
                    || $puppy->expires_at->lessThanOrEqualTo(now())) {
                    throw new PetUnavailable('This puppy is no longer available to list.');
                }
                $puppy->forceFill(['status' => $price === null ? 'pending' : 'listed', 'sale_price' => $price])->save();
            }, attempts: 3);
        } catch (PetUnavailable $exception) {
            $this->lifecycle->synchronize($puppyId);
            throw $exception;
        }
    }
}
