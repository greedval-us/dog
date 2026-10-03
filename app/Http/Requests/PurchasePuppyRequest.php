<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PurchasePuppyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'expected_price' => ['required', 'integer', 'min:1'],
            'operation_token' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => __('Dog name')];
    }
}
