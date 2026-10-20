<?php

namespace App\Http\Requests;

use App\Modules\Pets\Actions\PublishPetSale;
use Illuminate\Contracts\Validation\ValidationRule;

class PublishPetSaleRequest extends PetOwnerRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['price' => ['required', 'integer', 'min:1', 'max:'.PublishPetSale::MAXIMUM_PRICE]];
    }
}
