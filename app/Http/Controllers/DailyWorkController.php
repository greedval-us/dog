<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteDailyWorkRequest;
use App\Models\User;
use App\Modules\Players\Actions\CompleteDailyWork;
use App\Modules\Players\Exceptions\WorkUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DailyWorkController extends Controller
{
    public function store(CompleteDailyWorkRequest $request, CompleteDailyWork $work): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $shift = $work->handle($user, $request->integer('work_type_id'));
        } catch (WorkUnavailable $exception) {
            throw ValidationException::withMessages(['work_type_id' => __($exception->getMessage())]);
        }

        $message = $shift->wasRecentlyCreated
            ? ($shift->gems_reward > 0
                ? __('Shift complete! You earned :coins coins and :gems gems.', ['coins' => $shift->coins_reward, 'gems' => $shift->gems_reward])
                : __('Shift complete! You earned :coins coins.', ['coins' => $shift->coins_reward]))
            : __('You have already worked today. Come back tomorrow.');
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('players.show', $user->username);
    }
}
