<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseShopItemRequest;
use App\Models\User;
use App\Modules\Inventory\Actions\PurchaseItem;
use App\Modules\Inventory\Exceptions\ItemUnavailable;
use App\Modules\Inventory\Queries\GetShopCatalogue;
use App\Modules\Players\Exceptions\InsufficientFunds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ShopController extends Controller
{
    public function index(Request $request, GetShopCatalogue $catalogue): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validate([
            'category' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->where('is_active', true)],
            'cursor' => ['nullable', 'string', 'max:1000'],
        ]);
        $categoryId = isset($validated['category']) ? (int) $validated['category'] : null;

        return Inertia::render('Shop', [
            ...$catalogue->handle($user, app()->getLocale(), $categoryId),
            'selectedCategory' => $categoryId,
            'purchaseToken' => (string) Str::uuid(),
        ]);
    }

    public function store(PurchaseShopItemRequest $request, PurchaseItem $purchase): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $purchase->handle($user, $request->toData());
        } catch (ItemUnavailable $exception) {
            throw ValidationException::withMessages(['purchase' => __($exception->getMessage())]);
        } catch (InsufficientFunds) {
            throw ValidationException::withMessages(['purchase' => __('You do not have enough coins for this item.')]);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['purchase' => __('Refresh the shop before making another purchase.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The item has been added to your inventory.')]);

        return back();
    }
}
