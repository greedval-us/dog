<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrainPetSkillRequest;
use App\Models\User;
use App\Modules\Pets\Actions\TrainPetSkill;
use App\Modules\Pets\DTO\TrainPetSkillData;
use App\Modules\Pets\Exceptions\PetUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PetSkillController extends Controller
{
    public function store(TrainPetSkillRequest $request, int $pet, TrainPetSkill $train): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $train->handle($user, $pet, new TrainPetSkillData(
                skillId: (int) $request->validated('skill_id'),
                level: (int) $request->validated('level'),
                expectedPrice: (int) $request->validated('expected_price'),
                token: $request->validated('token'),
            ));
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['skill' => __($exception->getMessage())]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The instructor taught your dog a new skill level.')]);

        return to_route('dashboard', ['pet' => $pet]);
    }
}
