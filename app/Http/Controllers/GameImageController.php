<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameImageController extends Controller
{
    public function breed(string $breed, string $variant): StreamedResponse
    {
        abort_unless(in_array($breed, config('doglive.illustrated_breeds'), true), 404);
        abort_unless(in_array($variant, ['portrait', 'icon'], true), 404);

        return $this->image('breeds/'.$breed.'/'.$variant.'.png');
    }

    public function scene(): StreamedResponse
    {
        return $this->image('scenes/pet-profile.png');
    }

    private function image(string $path): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, headers: [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
