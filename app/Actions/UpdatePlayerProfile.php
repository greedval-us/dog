<?php

namespace App\Actions;

use App\Data\UpdatePlayerProfileData;
use App\Models\User;

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
