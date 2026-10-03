<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class ListPuppyRequest extends PuppyOwnerRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['price' => ['required', 'integer', 'min:1', 'max:1000000']];
    }
}
