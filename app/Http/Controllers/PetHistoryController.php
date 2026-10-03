<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\User;
use App\Modules\Pets\Queries\GetPetHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PetHistoryController extends Controller
{
    public function index(Request $request, Pet $pet, GetPetHistory $history): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $pet->user_id === $user->id, 404);
        $input = $request->validate([
            'kind' => ['sometimes', Rule::in(['action', 'thought'])],
            'period' => ['sometimes', 'integer', Rule::in([7, 30])],
            'event' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_.]*$/'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ]);
        $cursor = null;
        if (! empty($input['cursor'])) {
            $cursor = Cursor::fromEncoded($input['cursor']);
            abort_if($cursor === null, 422);
            $parameters = $cursor->toArray();
            abort_unless(is_bool($parameters['_pointsToNextItems'] ?? null), 422);
            Validator::make($parameters, [
                'id' => ['required', 'regex:/^[1-9][0-9]{0,17}$/'],
                'occurred_at' => ['required', 'string', 'date_format:Y-m-d H:i:s'],
            ])->validate();
        }

        return response()->json($history->handle(
            $user, $pet->id, app()->getLocale(), $input['kind'] ?? 'action',
            (int) ($input['period'] ?? 7), $input['event'] ?? null, $cursor,
        ));
    }
}
