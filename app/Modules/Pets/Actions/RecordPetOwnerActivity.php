<?php

namespace App\Modules\Pets\Actions;

use App\Models\User;
use App\Modules\Pets\Calculators\PetBoardingRules;
use App\Modules\Pets\Enums\PetBoardingPlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Preserve completed absences before moving the activity clock, including while an event is pending. */
final class RecordPetOwnerActivity
{
    public function __construct(private PetBoardingRules $rules) {}

    public function handle(User $user, CarbonImmutable $at): void
    {
        $changed = User::query()->whereKey($user->id)->where('boarding_plan', PetBoardingPlan::Off->value)
            ->where(fn (Builder $query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $at))
            ->update(['last_seen_at' => $at]);
        if ($changed === 1) {
            return;
        }
        $current = User::query()->findOrFail($user->id);
        if ($current->boarding_plan === PetBoardingPlan::Off || $current->last_seen_at?->greaterThanOrEqualTo($at)) {
            return;
        }
        DB::transaction(function () use ($user, $at): void {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($owner->last_seen_at !== null && $owner->last_seen_at->greaterThanOrEqualTo($at)) {
                return;
            }
            if ($owner->boarding_plan !== PetBoardingPlan::Off && $owner->boarding_enabled_at !== null) {
                $idleAt = $owner->boarding_enabled_at->max($owner->last_seen_at ?? $owner->boarding_enabled_at)
                    ->addSeconds($this->rules->inactivitySeconds());
                if ($idleAt->lessThan($at)) {
                    $owner->boarding_absences = [...($owner->boarding_absences ?? []), [
                        ...$this->rules->period($owner->boarding_plan, $idleAt->getTimestamp(), $at->getTimestamp()),
                        'hourly_price' => $this->rules->hourlyPrice($owner->boarding_plan),
                    ]];
                }
            }
            $owner->last_seen_at = $at;
            $owner->save();
        }, attempts: 3);
    }
}
