<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseVeterinaryServiceRequest;
use App\Models\User;
use App\Modules\Pets\Actions\PurchaseVeterinaryService;
use App\Modules\Pets\DTO\PurchaseVeterinaryServiceData;
use App\Modules\Pets\Enums\VeterinaryService;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetVeterinarian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VeterinarianController extends Controller
{
    public function index(Request $request, GetVeterinarian $query): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $data = $request->validate(['pet' => ['nullable', 'integer', 'min:1']]);
        if (isset($data['pet'])) {
            $selected = $user->pets()->whereKey($data['pet'])->firstOrFail();
            if (! $selected->isActive()) {
                return to_route('players.memorial.show', ['user' => $user->username, 'pet' => $selected->id]);
            }
        }

        return Inertia::render('Veterinarian', [
            'clinic' => $query->handle($user, isset($data['pet']) ? (int) $data['pet'] : null, app()->getLocale()),
        ]);
    }

    public function store(PurchaseVeterinaryServiceRequest $request, PurchaseVeterinaryService $purchase): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $petId = (int) $request->validated('pet_id');
        $episodeId = $request->validated('disease_episode_id');

        try {
            $visit = $purchase->handle($user, new PurchaseVeterinaryServiceData(
                $petId, VeterinaryService::from($request->validated('service')),
                $episodeId === null ? null : (int) $episodeId,
                (int) $request->validated('expected_price'), $request->validated('token'),
            ));
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['visit' => __($exception->getMessage())]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __($visit->wasRecentlyCreated
            ? 'The veterinary service is complete.' : 'This veterinary visit was already completed. You have not been charged again.')]);

        return to_route('veterinarian.index', $user->pets()->whereKey($petId)->exists() ? ['pet' => $petId] : []);
    }
}
