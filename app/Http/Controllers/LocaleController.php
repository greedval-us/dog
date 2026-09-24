<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->validated('locale');

        $user = $request->user('web');

        if ($user instanceof User) {
            $user->locale = $locale;
            $user->save();
        }

        return back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}
