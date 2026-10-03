<?php

namespace App\Modules\Pets\Services;

use App\Models\BreedingLitter;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Players\Enums\PlayerStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class PuppyLifecycle
{
    /** Births and handoffs commit independently of a subsequent placement or listing refusal. */
    public function synchronize(?int $puppyId = null): int
    {
        $at = CarbonImmutable::now();
        $settled = 0;
        $births = BreedingLitter::query()->whereNull('delivered_at')->where('born_at', '<=', $at);
        if ($puppyId !== null) {
            $births->whereHas('puppies', fn (Builder $puppies): Builder => $puppies->whereKey($puppyId));
        }
        foreach ($births->select('id')->lazyById(100) as $litter) {
            $ownerIds = Puppy::query()->where('litter_id', $litter->id)->where('status', 'unborn')
                ->whereNotNull('user_id')->pluck('user_id')->unique()->sort()->values()->all();
            $settled += DB::transaction(function () use ($litter, $ownerIds, $at): int {
                $owners = User::query()->whereIn('id', $ownerIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $birth = BreedingLitter::query()->lockForUpdate()->find($litter->id);
                if ($birth === null || $birth->delivered_at !== null || $birth->born_at->isAfter($at)) {
                    return 0;
                }
                $puppies = Puppy::query()->where('litter_id', $birth->id)->where('status', 'unborn')
                    ->orderBy('id')->lockForUpdate()->get();
                foreach ($puppies as $puppy) {
                    $owner = $owners->get($puppy->user_id);
                    $belongsToActivePlayer = $owner?->status === PlayerStatus::Active;
                    $puppy->forceFill([
                        'status' => $belongsToActivePlayer ? 'pending' : 'kennel',
                        'user_id' => $belongsToActivePlayer ? $puppy->user_id : null,
                        'sale_price' => null,
                    ])->save();
                }
                $birth->forceFill(['delivered_at' => $at])->save();

                return $puppies->count();
            }, attempts: 3);
        }
        $expired = Puppy::query()->whereIn('status', ['pending', 'listed'])
            ->where(fn (Builder $query): Builder => $query->where('expires_at', '<=', $at)->orWhereNull('user_id'));
        if ($puppyId !== null) {
            $expired->whereKey($puppyId);
        }
        foreach ($expired->select(['id', 'user_id'])->lazyById(100) as $candidate) {
            $settled += DB::transaction(function () use ($candidate, $at): int {
                if ($candidate->user_id !== null) {
                    User::query()->whereKey($candidate->user_id)->lockForUpdate()->first();
                }
                $puppy = Puppy::query()->lockForUpdate()->find($candidate->id);
                if ($puppy === null || ! in_array($puppy->status, ['pending', 'listed'], true)
                    || ($puppy->user_id !== null && $puppy->expires_at->isAfter($at))) {
                    return 0;
                }
                $puppy->forceFill(['status' => 'kennel', 'user_id' => null, 'sale_price' => null])->save();

                return 1;
            }, attempts: 3);
        }

        return $settled;
    }
}
