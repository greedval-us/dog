<?php

namespace App\Http\Controllers;

use App\Http\PrivateImageResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameImageController extends Controller
{
    public function breed(Request $request, PrivateImageResponse $image, string $breed, string $variant): StreamedResponse
    {
        abort_unless(in_array($breed, config('doglive.illustrated_breeds'), true), 404);
        abort_unless(in_array($variant, ['portrait', 'icon'], true), 404);

        return $image->handle($request, 'breeds/'.$breed.'/'.$variant.'.png');
    }

    public function scene(Request $request, PrivateImageResponse $image): StreamedResponse
    {
        return $image->handle($request, 'scenes/pet-profile.png');
    }
}
