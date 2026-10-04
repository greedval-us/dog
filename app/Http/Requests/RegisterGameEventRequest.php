<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class RegisterGameEventRequest extends UpdateGameEventRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'pet_id' => ['required', 'integer', 'min:1'],
            'fee' => ['required', 'integer', 'min:0', 'max:1000000'],
            'token' => ['required', 'uuid'],
        ];
    }
}
