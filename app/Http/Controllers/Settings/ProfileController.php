<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Modules\Players\Actions\UpdatePlayerAvatar;
use App\Modules\Players\Actions\UpdatePlayerProfile;
use App\Modules\Players\Queries\GetPlayerProfile;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request, GetPlayerProfile $profile): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'player' => $profile->handle($user)->toArray(),
            'avatarLimits' => config('doglive.avatar'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, UpdatePlayerProfile $updateProfile): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $updateProfile->handle($user, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, UpdatePlayerAvatar $updateAvatar): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        Auth::logout();

        DB::transaction(function () use ($user, $updateAvatar): void {
            $updateAvatar->handle($user, null);
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
