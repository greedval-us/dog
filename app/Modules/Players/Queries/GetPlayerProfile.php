<?php

namespace App\Modules\Players\Queries;

use App\Models\Pet;
use App\Models\User;
use App\Modules\Players\DTO\PlayerProfileData;
use Illuminate\Database\Eloquent\Builder;

final class GetPlayerProfile
{
    public function __construct(private GetPlayerGameStatistics $gameStatistics) {}

    public function handle(User $user): PlayerProfileData
    {
        $player = User::query()->withCount(['pets' => $this->activePets(...)])->findOrFail($user->id);

        return PlayerProfileData::fromModel($player, $player->pets_count, $this->gameStatistics->handle($player));
    }

    /** @param Builder<Pet> $query */
    private function activePets(Builder $query): void
    {
        $query->active();
    }
}
