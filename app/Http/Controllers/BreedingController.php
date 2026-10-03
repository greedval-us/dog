<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartBreedingRequest;
use App\Models\User;
use App\Modules\Pets\Actions\StartBreeding;
use App\Modules\Pets\Exceptions\BreedingUnavailable;
use App\Modules\Pets\Queries\GetBreedingBoard;
use App\Modules\Pets\Services\PuppyLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BreedingController extends Controller
{
    public function index(Request $request, GetBreedingBoard $board, PuppyLifecycle $puppies): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $user->refresh();
        $puppies->synchronize();
        $selection = $request->validate(['pet' => ['nullable', 'integer', 'min:1'], 'kind' => ['nullable', 'in:listing,partner'], 'partner' => ['nullable', 'integer', 'min:1'], 'cursor' => ['nullable', 'string', 'max:1000']]);

        return Inertia::render('breeding/Index', $board->handle($user, app()->getLocale(), isset($selection['pet']) ? (int) $selection['pet'] : null, $selection['kind'] ?? null, isset($selection['partner']) ? (int) $selection['partner'] : null));
    }

    public function store(StartBreedingRequest $request, StartBreeding $start): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        try {
            $start->handle($user, (int) $request->validated('pet_id'), $request->validated('kind'), (int) $request->validated('partner_id'), (int) $request->validated('expected_price'), $request->validated('operation_token'));
        } catch (BreedingUnavailable $exception) {
            throw ValidationException::withMessages(['breeding' => __(str_replace('breeding.errors.', 'breeding.', $exception->getMessage()))]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('breeding.started')]);

        return to_route('puppies.index');
    }
}
