<?php

namespace App\Modules\Pets\Services;

use App\Models\BreedingLitter;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Players\Enums\AchievementMetric;
use App\Modules\Players\Enums\PlayerStatus;
use App\Modules\Players\Services\PlayerProgress;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PuppyLifecycle
{
    public function __construct(private PlayerProgress $progress) {}

    /** Births and handoffs commit independently of a subsequent placement or listing refusal. */
    public function synchronize(?int $puppyId = null): int
    {
        return $this->synchronizeScope($puppyId, null, null);
    }

    public function synchronizeForOwner(User $owner, int $limit = 20): int
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Puppy synchronization batch size must be between 1 and 100.');
        }

        return $this->synchronizeScope(null, $owner->id, $limit);
    }

    private function synchronizeScope(?int $puppyId, ?int $ownerId, ?int $limit): int
    {
        $at = CarbonImmutable::now();
        $settled = 0;
        $births = BreedingLitter::query()->whereNull('delivered_at')->where('born_at', '<=', $at);
        if ($puppyId !== null) {
            $births->whereHas('puppies', fn (Builder $puppies): Builder => $puppies->whereKey($puppyId));
        }
        if ($ownerId !== null) {
            $births->whereHas('puppies', fn (Builder $puppies): Builder => $puppies->where('user_id', $ownerId));
        }
        $litters = $limit === null ? $births->select(['id', 'initiator_id'])->lazyById(100) : $births->select(['id', 'initiator_id'])->orderBy('id')->limit($limit)->get();
        foreach ($litters as $litter) {
            $ownerIds = Puppy::query()->where('litter_id', $litter->id)->where('status', 'unborn')
                ->whereNotNull('user_id')->pluck('user_id')->push($litter->initiator_id)->filter()->unique()->sort()->values()->all();
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
                $initiator = $owners->get($birth->initiator_id);
                if ($initiator !== null) {
                    $this->progress->refreshAchievements($initiator, [AchievementMetric::LittersBorn, AchievementMetric::PuppiesBorn]);
                }

                return $puppies->count();
            }, attempts: 3);
        }
        $expired = Puppy::query()->whereIn('status', ['pending', 'listed'])
            ->where(fn (Builder $query): Builder => $query->where('expires_at', '<=', $at)->orWhereNull('user_id'));
        if ($puppyId !== null) {
            $expired->whereKey($puppyId);
        }
        if ($ownerId !== null) {
            $expired->where('user_id', $ownerId);
        }
        $candidates = $limit === null ? $expired->select(['id', 'user_id'])->lazyById(100) : $expired->select(['id', 'user_id'])->orderBy('id')->limit($limit)->get();
        foreach ($candidates as $candidate) {
            $settled += DB::transaction(function () use ($candidate, $at, $ownerId): int {
                if ($candidate->user_id !== null) {
                    User::query()->whereKey($candidate->user_id)->lockForUpdate()->first();
                }
                $puppy = Puppy::query()->lockForUpdate()->find($candidate->id);
                if ($puppy === null || ! in_array($puppy->status, ['pending', 'listed'], true)
                    || ($ownerId !== null && $puppy->user_id !== $ownerId)
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
