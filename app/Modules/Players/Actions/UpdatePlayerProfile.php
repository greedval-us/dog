<?php

namespace App\Modules\Players\Actions;

use App\Models\User;
use App\Modules\Players\DTO\UpdatePlayerProfileData;

final class UpdatePlayerProfile
{
    public function handle(User $user, UpdatePlayerProfileData $data): void
    {
        $user->name = $data->name;
        $user->email = $data->email;

        if ($data->bioProvided) {
            $user->bio = $data->bio;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
    }
}
