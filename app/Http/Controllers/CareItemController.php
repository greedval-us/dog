<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Pets\Queries\GetCareItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CareItemController extends Controller
{
    public function __invoke(Request $request, GetCareItems $getItems): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $data = $request->validate([
            'category' => ['required', Rule::in(['food', 'collars', 'leashes', 'toys', 'care', 'clothing', 'sports'])],
            'uses' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ]);

        return response()->json($getItems->handle($user, app()->getLocale(), $data['category'], $request->integer('uses', 1), $data['cursor'] ?? null));
    }
}
