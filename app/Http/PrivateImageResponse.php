<?php

namespace App\Http;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PrivateImageResponse
{
    public function handle(Request $request, string $path): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $webpPath = substr($path, 0, -4).'.webp';
        if ($disk->exists($webpPath)) {
            $path = $webpPath;
        }

        $response = $disk->response($path, headers: [
            'Content-Type' => str_ends_with($path, '.webp') ? 'image/webp' : 'image/png',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setLastModified((new DateTimeImmutable)->setTimestamp($disk->lastModified($path)));
        $response->setEtag($disk->checksum($path) ?: null);
        $response->isNotModified($request);

        return $response;
    }
}
