<?php

namespace App\Http\Telegram;

use App\Models\Dog;
use App\Models\GameAsset;
use App\Models\User;
use App\Modules\Appearance\Queries\GetPetAppearance;
use Illuminate\Support\Facades\Storage;

final class TelegramPetImage
{
    public function __construct(private GetPetAppearance $appearance) {}

    public function forPet(User $user, int $petId): ?string
    {
        $appearance = $this->appearance->handle($user, $petId, $user->preferredLocale());
        if ($appearance->portraitId !== null) {
            $portrait = GameAsset::query()->find($appearance->portraitId);
            $path = $portrait?->mediaPath('image');
            $image = $path === null ? null : $this->privatePng($path);
            if ($image !== null) {
                return $image;
            }
        }

        return $this->forBreed($user->pets()->findOrFail($petId)->dog_id);
    }

    public function forBreed(int $dogId): ?string
    {
        $illustration = Dog::query()->find($dogId)?->illustration();

        return $illustration === null ? null : $this->privatePng('breeds/'.$illustration.'/portrait.png');
    }

    private function privatePng(string $path): ?string
    {
        if (! preg_match('#\A(?:appearance|breeds|scenes)/[a-z0-9_/-]+\.png\z#D', $path)) {
            return null;
        }

        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));
        if ($root === false || $file === false || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR)
            || ! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $size = filesize($file);
        $dimensions = @getimagesize($file);
        if ($size === false || $size > (float) config('telegraph.attachments.photo.max_size_mb', 10) * 1024 * 1024
            || $dimensions === false || $dimensions[2] !== IMAGETYPE_PNG) {
            return null;
        }

        [$width, $height] = $dimensions;
        $maxRatio = (float) config('telegraph.attachments.photo.max_ratio', 20);
        if ($width < 1 || $height < 1 || $width + $height > (int) config('telegraph.attachments.photo.height_width_sum_px', 10000)
            || $height / $width > $maxRatio || $width / $height > $maxRatio) {
            return null;
        }

        return $file;
    }
}
