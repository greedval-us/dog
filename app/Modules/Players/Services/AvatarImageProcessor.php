<?php

namespace App\Modules\Players\Services;

use App\Modules\Players\DTO\PlayerAvatarData;
use App\Modules\Players\Exceptions\InvalidAvatarImage;
use GdImage;

final class AvatarImageProcessor
{
    public function encode(PlayerAvatarData $data): string
    {
        $bytes = file_get_contents($data->temporaryPath);
        $dimensions = $bytes === false ? false : @getimagesizefromstring($bytes);

        if ($dimensions === false || strlen($bytes) > config('doglive.avatar.max_kilobytes') * 1024
            || max($dimensions[0], $dimensions[1]) > config('doglive.avatar.max_dimension')
            || ! in_array($dimensions[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new InvalidAvatarImage;
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new InvalidAvatarImage;
        }

        if ($dimensions[2] === IMAGETYPE_JPEG) {
            $exif = @exif_read_data($data->temporaryPath);
            $image = $this->orient($image, (int) ($exif['Orientation'] ?? 1));
        }

        $ratio = min(1, config('doglive.avatar.stored_dimension') / max(imagesx($image), imagesy($image)));
        $width = max(1, (int) round(imagesx($image) * $ratio));
        $height = max(1, (int) round(imagesy($image) * $ratio));
        $thumbnail = imagecreatetruecolor($width, $height);
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        ob_start();

        try {
            if (! imagewebp($thumbnail, quality: 80)) {
                throw new InvalidAvatarImage;
            }

            $encoded = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if ($encoded === false || $encoded === '') {
            throw new InvalidAvatarImage;
        }

        return $encoded;
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 8 => 90,
            6, 7 => -90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if ($rotated === false) {
            throw new InvalidAvatarImage;
        }

        return $rotated;
    }
}
