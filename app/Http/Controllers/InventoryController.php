<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Inventory\Queries\GetInventoryCatalogue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, GetInventoryCatalogue $inventory): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validate([
            'category' => ['nullable', 'integer', 'min:1'],
            'cursor' => ['nullable', 'string', 'max:1000'],
        ]);
        $categoryId = isset($validated['category']) ? (int) $validated['category'] : null;
        $catalogue = $inventory->handle($user, app()->getLocale(), $categoryId);

        abort_if($categoryId !== null && ! in_array($categoryId, array_column($catalogue['categories'], 'id'), true), 404);

        return Inertia::render('Inventory', [
            ...$catalogue,
            'selectedCategory' => $categoryId,
        ]);
    }
}
