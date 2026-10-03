<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class KeepPuppyRequest extends PuppyOwnerRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'operation_token' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => __('Dog name')];
    }
}
