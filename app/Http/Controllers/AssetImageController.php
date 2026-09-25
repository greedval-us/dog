<?php

namespace App\Http\Controllers;

use App\Models\GameAsset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetImageController extends Controller
{
    public function __invoke(GameAsset $asset, string $variant): StreamedResponse
    {
        abort_unless($asset->is_active, 404);
        $path = $asset->mediaPath($variant);
        abort_if($path === null, 404);

        return Storage::disk('local')->response($path, headers: [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
