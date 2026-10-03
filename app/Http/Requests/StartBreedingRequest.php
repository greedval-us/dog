<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StartBreedingRequest extends FormRequest
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
            'pet_id' => ['required', 'integer', 'min:1'],
            'kind' => ['required', 'in:listing,partner'],
            'partner_id' => ['required', 'integer', 'min:1'],
            'expected_price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'operation_token' => ['required', 'uuid'],
        ];
    }
}
