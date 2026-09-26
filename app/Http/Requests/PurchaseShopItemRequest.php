<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Modules\Inventory\DTO\PurchaseItemData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseShopItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'offer_id' => ['required', 'integer', Rule::exists('shop_offers', 'id')],
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'expected_currency' => ['required', Rule::in(['coins'])],
            'expected_price' => ['required', 'integer', 'min:1'],
            'purchase_token' => ['required', 'uuid'],
        ];
    }

    public function toData(): PurchaseItemData
    {
        return new PurchaseItemData(
            offerId: (int) $this->validated('offer_id'),
            itemId: (int) $this->validated('item_id'),
            expectedCurrency: $this->validated('expected_currency'),
            expectedPrice: (int) $this->validated('expected_price'),
            token: $this->validated('purchase_token'),
        );
    }
}
