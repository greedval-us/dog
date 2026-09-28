<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Modules\Pets\Calculators\PetCareRules;
use App\Modules\Pets\Enums\DogSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPetCareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, array<mixed>> */
    public function rules(PetCareRules $rules): array
    {
        return [
            'variant' => ['required', Rule::in(array_keys($rules->options(DogSize::Medium)))],
            'token' => ['required', 'uuid'],
            'items' => ['present', 'array:food,collars,leashes,toys,care,clothing,sports'],
            'items.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    /** @return array<string, int> */
    public function itemIds(): array
    {
        return array_map(fn ($id): int => (int) $id, $this->validated('items'));
    }
}
