<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TrainPetSkillRequest extends FormRequest
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
            'skill_id' => ['required', 'integer', 'min:1'],
            'level' => ['required', 'integer', 'between:1,5'],
            'expected_price' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'token' => ['required', 'uuid'],
        ];
    }
}
