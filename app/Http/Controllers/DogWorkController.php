<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteDogWorkRequest;
use App\Http\Requests\StartDogWorkRequest;
use App\Models\User;
use App\Modules\Pets\Actions\CompleteDogWork;
use App\Modules\Pets\Actions\GenerateDogWorkBoard;
use App\Modules\Pets\Actions\StartDogWork;
use App\Modules\Pets\DTO\StartDogWorkData;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetDogWorkBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DogWorkController extends Controller
{
    public function index(Request $request, GenerateDogWorkBoard $generate, GetDogWorkBoard $query): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $data = $request->validate(['pet' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('DogWork', [
            'board' => $query->handle($user, $generate->handle(), isset($data['pet']) ? (int) $data['pet'] : null, app()->getLocale()),
        ]);
    }

    public function store(StartDogWorkRequest $request, StartDogWork $start): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $petId = (int) $request->validated('pet_id');

        try {
            $start->handle($user, $petId, new StartDogWorkData((int) $request->validated('offer_id'), $request->validated('token')));
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['work' => __($exception->getMessage())]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your dog has started work.')]);

        return to_route('dog-work.index', ['pet' => $petId]);
    }

    public function complete(CompleteDogWorkRequest $request, CompleteDogWork $complete): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $shift = $complete->handle($user, $request->validated('token'));
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['work' => __($exception->getMessage())]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work completed. The reward is in your wallet.')]);

        return to_route('dog-work.index', ['pet' => $shift->pet_id]);
    }
}
