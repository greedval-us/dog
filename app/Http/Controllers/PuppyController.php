<?php

namespace App\Http\Controllers;

use App\Http\Requests\KeepPuppyRequest;
use App\Http\Requests\ListPuppyRequest;
use App\Http\Requests\PuppyOwnerRequest;
use App\Http\Requests\PurchasePuppyRequest;
use App\Models\Puppy;
use App\Models\User;
use App\Modules\Pets\Actions\KeepPuppy;
use App\Modules\Pets\Actions\ListPuppyForSale;
use App\Modules\Pets\Actions\PurchasePuppy;
use App\Modules\Pets\Actions\SurrenderPuppy;
use App\Modules\Pets\Exceptions\PetUnavailable;
use App\Modules\Pets\Queries\GetPlayerPuppies;
use App\Modules\Pets\Queries\GetPuppyMarket;
use App\Modules\Pets\Services\PetLifecycle;
use App\Modules\Pets\Services\PuppyLifecycle;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PuppyController extends Controller
{
    public function index(Request $request, GetPlayerPuppies $puppies, PuppyLifecycle $puppyLifecycle, PetLifecycle $lifecycle): Response
    {
        $user = $this->player($request);
        $validated = $request->validate(['parent' => ['nullable', 'integer', 'min:1'], 'cursor' => ['nullable', 'string', 'max:1000']]);
        $parentId = isset($validated['parent']) ? (int) $validated['parent'] : null;
        abort_if($parentId !== null && ! $user->pets()->whereKey($parentId)->exists(), 404);
        $puppyLifecycle->synchronize();
        $lifecycle->synchronizeOwner($user);
        $user->refresh();

        return Inertia::render('puppies/Index', [
            ...$puppies->handle($user, app()->getLocale(), $parentId),
            'parentId' => $parentId,
            'freeSlots' => max(0, $user->pet_slots - $user->pets()->active()->count()),
            'blocked' => $user->status !== PlayerStatus::Active,
        ]);
    }

    public function market(Request $request, GetPuppyMarket $puppies, PuppyLifecycle $puppyLifecycle, PetLifecycle $lifecycle): Response
    {
        $user = $this->player($request);
        $validated = $request->validate(['source' => ['nullable', 'in:players,kennel'], 'cursor' => ['nullable', 'string', 'max:1000']]);
        $source = $validated['source'] ?? 'players';
        $puppyLifecycle->synchronize();
        $lifecycle->synchronizeOwner($user);
        $user->refresh();

        return Inertia::render('puppies/Market', [
            ...$puppies->handle(app()->getLocale(), $source),
            'source' => $source,
            'freeSlots' => max(0, $user->pet_slots - $user->pets()->active()->count()),
            'blocked' => $user->status !== PlayerStatus::Active,
            'kennelPrice' => (int) config('doglive.kennel_price'),
        ]);
    }

    public function keep(KeepPuppyRequest $request, Puppy $puppy, KeepPuppy $keep): RedirectResponse
    {
        try {
            $placement = $keep->handle($this->player($request), $puppy->id, $request->validated('name'), $request->validated('operation_token'));
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['puppy' => __($exception->getMessage())]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your new dog is home!')]);

        return to_route('dashboard', ['pet' => $placement->pet_id]);
    }

    public function list(ListPuppyRequest $request, Puppy $puppy, ListPuppyForSale $list): RedirectResponse
    {
        try {
            $list->handle($this->player($request), $puppy->id, (int) $request->validated('price'));
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['puppy' => __($exception->getMessage())]);
        }

        return back();
    }

    public function unlist(PuppyOwnerRequest $request, Puppy $puppy, ListPuppyForSale $list): RedirectResponse
    {
        try {
            $list->handle($this->player($request), $puppy->id, null);
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['puppy' => __($exception->getMessage())]);
        }

        return back();
    }

    public function surrender(PuppyOwnerRequest $request, Puppy $puppy, SurrenderPuppy $surrender): RedirectResponse
    {
        try {
            $surrender->handle($this->player($request), $puppy->id);
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['puppy' => __($exception->getMessage())]);
        }

        return back();
    }

    public function purchase(PurchasePuppyRequest $request, Puppy $puppy, PurchasePuppy $purchase): RedirectResponse
    {
        try {
            $placement = $purchase->handle($this->player($request), $puppy->id, $request->validated('name'),
                (int) $request->validated('expected_price'), $request->validated('operation_token'));
        } catch (PetUnavailable $exception) {
            throw ValidationException::withMessages(['puppy' => __($exception->getMessage())]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your new dog is home!')]);

        return to_route('dashboard', ['pet' => $placement->pet_id]);
    }

    private function player(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
