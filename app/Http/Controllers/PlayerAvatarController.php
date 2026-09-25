<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePlayerAvatarRequest;
use App\Models\User;
use App\Modules\Players\Actions\UpdatePlayerAvatar;
use App\Modules\Players\Exceptions\InvalidAvatarImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlayerAvatarController extends Controller
{
    public function show(User $user): StreamedResponse
    {
        $disk = Storage::disk('avatars');
        abort_if($user->avatar_path === null || ! $disk->exists($user->avatar_path), 404);

        return $disk->response($user->avatar_path, headers: [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    public function store(UpdatePlayerAvatarRequest $request, UpdatePlayerAvatar $updateAvatar): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $updateAvatar->handle($user, $request->toData());
        } catch (InvalidAvatarImage) {
            throw ValidationException::withMessages(['avatar' => __('This image could not be read. Choose another image.')]);
        } catch (FilesystemException $exception) {
            report($exception);

            throw ValidationException::withMessages(['avatar' => __('The avatar could not be saved. Please try again.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Avatar updated.')]);

        return back();
    }

    public function destroy(Request $request, UpdatePlayerAvatar $updateAvatar): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $updateAvatar->handle($user, null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Avatar removed.')]);

        return back();
    }
}
