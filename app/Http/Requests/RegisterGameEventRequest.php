<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterGameEventRequest extends FormRequest
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
            'fee' => ['required', 'integer', 'min:0', 'max:1000000'],
            'token' => ['required', 'uuid'],
            'plan' => ['required', 'array:stages,offspring_ids'],
            'plan.stages' => ['required', 'array', 'size:3'],
            'plan.stages.*' => ['required', 'in:careful,balanced,bold'],
            'plan.offspring_ids' => ['sometimes', 'array', 'max:5'],
            'plan.offspring_ids.*' => ['integer', 'min:1', 'distinct'],
            'gear_ids' => ['present', 'array', 'max:4'],
            'gear_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }
}
