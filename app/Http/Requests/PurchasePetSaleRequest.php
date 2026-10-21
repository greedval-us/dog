<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Modules\Pets\Actions\PublishPetSale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PurchasePetSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'expected_price' => ['required', 'integer', 'min:1', 'max:'.PublishPetSale::MAXIMUM_PRICE],
            'purchase_token' => ['required', 'uuid'],
        ];
    }
}
