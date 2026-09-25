<?php

namespace App\Modules\Players\Queries;

use App\Models\User;
use App\Modules\Players\DTO\PlayerProfileData;

final class GetPlayerProfile
{
    public function handle(User $user): PlayerProfileData
    {
        $player = User::query()->withCount('pets')->findOrFail($user->id);

        return PlayerProfileData::fromModel($player, $player->pets_count);
    }
}
