<?php

namespace App\Queries;

use App\Data\PlayerProfileData;
use App\Models\User;

final class GetPlayerProfile
{
    public function handle(User $user): PlayerProfileData
    {
        $player = User::query()->withCount('pets')->findOrFail($user->id);

        return PlayerProfileData::fromModel($player, $player->pets_count);
    }
}
