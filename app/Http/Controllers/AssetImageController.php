<?php

namespace App\Http\Controllers;

use App\Http\PrivateImageResponse;
use App\Models\GameAsset;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetImageController extends Controller
{
    public function __invoke(Request $request, PrivateImageResponse $image, GameAsset $asset, string $variant): StreamedResponse
    {
        abort_unless($asset->is_active, 404);
        $path = $asset->mediaPath($variant);
        abort_if($path === null, 404);

        return $image->handle($request, $path);
    }
}
