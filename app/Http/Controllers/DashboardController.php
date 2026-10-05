<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Appearance\Queries\GetPetAppearance;
use App\Modules\Pets\Queries\GetPetCare;
use App\Modules\Pets\Queries\GetPetCareer;
use App\Modules\Pets\Queries\GetPetSkills;
use App\Modules\Pets\Queries\GetPetSlots;
use App\Modules\Pets\Queries\GetPrimaryPet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GetPrimaryPet $getPrimaryPet, GetPetAppearance $getAppearance, GetPetSlots $getSlots, GetPetCare $getCare, GetPetSkills $getSkills, GetPetCareer $getCareer): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $input = $request->validate([
            'pet' => ['sometimes', 'integer', 'min:1'],
            'career_cursor' => ['nullable', 'string', 'max:2048'],
        ]);
        $cursor = null;
        if (! empty($input['career_cursor'])) {
            $cursor = Cursor::fromEncoded($input['career_cursor']);
            abort_if($cursor === null, 422);
            abort_unless(is_bool($cursor->toArray()['_pointsToNextItems'] ?? null), 422);
            Validator::make($cursor->toArray(), [
                'id' => ['required', 'regex:/^[1-9][0-9]{0,17}$/'],
                'completed_at' => ['required', 'string', 'date_format:Y-m-d H:i:s'],
            ])->validate();
        }
        $selectedPet = $request->has('pet')
            ? $user->pets()->whereKey($request->integer('pet'))->firstOrFail()
            : $user->pets()->active()->oldest('id')->first();
        $petId = $selectedPet?->id;

        if ($selectedPet !== null && ! $selectedPet->isActive()) {
            return to_route('players.memorial.show', ['user' => $user->username, 'pet' => $petId]);
        }

        return Inertia::render('Dashboard', [
            'canClaimStarterPet' => fn () => $user->canClaimStarterPet(),
            'slots' => fn () => $getSlots->handle($user),
            'pet' => fn () => $petId === null ? null : $getPrimaryPet->handle($user, app()->getLocale(), $petId)?->toArray(),
            'career' => Inertia::optional(fn () => $petId === null ? null : $getCareer->handle($user, $petId, app()->getLocale(), $cursor)),
            'care' => Inertia::defer(fn () => $petId === null ? null : $getCare->handle($user, $petId, app()->getLocale()), 'care', rescue: true),
            'skills' => Inertia::defer(fn () => $petId === null ? null : $getSkills->handle($user, $petId, app()->getLocale()), 'care', rescue: true),
            'appearance' => Inertia::defer(fn () => $petId === null ? null : $getAppearance->handle($user, $petId, app()->getLocale())->toArray(), 'appearance', rescue: true),
        ]);
    }
}
