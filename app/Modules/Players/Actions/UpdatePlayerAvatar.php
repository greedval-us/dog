<?php

namespace App\Modules\Players\Actions;

use App\Models\User;
use App\Modules\Players\DTO\PlayerAvatarData;
use App\Modules\Players\Services\AvatarImageProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToWriteFile;
use Throwable;

final class UpdatePlayerAvatar
{
    public function __construct(private AvatarImageProcessor $images) {}

    public function handle(User $user, ?PlayerAvatarData $data): void
    {
        $disk = Storage::disk('avatars');
        $path = null;

        if ($data !== null) {
            $encoded = $this->images->encode($data);
            $path = $user->id.'/'.Str::uuid().'.webp';

        }

        try {
            if ($path !== null && ! $disk->put($path, $encoded, ['visibility' => 'private'])) {
                throw UnableToWriteFile::atLocation($path);
            }

            DB::transaction(function () use ($user, $path, $disk): void {
                $player = User::query()->lockForUpdate()->findOrFail($user->id);
                $previousPath = $player->avatar_path;
                $player->avatar_path = $path;
                $player->save();

                if ($previousPath !== null) {
                    DB::afterCommit(fn () => rescue(fn () => $disk->delete($previousPath)));
                }
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                rescue(fn () => $disk->delete($path));
            }

            throw $exception;
        }
    }
}
