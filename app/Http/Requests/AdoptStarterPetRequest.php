<?php

namespace App\Http\Requests;

use App\Data\AdoptStarterPetData;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdoptStarterPetRequest extends FormRequest
{
    public function toData(): AdoptStarterPetData
    {
        return new AdoptStarterPetData(
            dogId: (int) $this->validated('dog_id'),
            name: $this->validated('name'),
        );
    }

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'dog_id' => ['required', 'integer', Rule::exists('dog', 'id')->where('is_starter', true)],
            'name' => ['required', 'string', 'max:64'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['dog_id' => __('Breed'), 'name' => __('Dog name')];
    }
}
