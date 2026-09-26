<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Modules\Kennel\DTO\PurchaseKennelPetData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseKennelPetRequest extends FormRequest
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
            'dog_id' => ['required', 'integer', Rule::exists('dog', 'id')->where('is_starter', true)],
            'name' => ['required', 'string', 'max:64'],
            'expected_price' => ['required', 'integer', 'min:1'],
            'adoption_token' => ['required', 'uuid'],
        ];
    }

    public function toData(): PurchaseKennelPetData
    {
        return new PurchaseKennelPetData(
            dogId: (int) $this->validated('dog_id'),
            name: $this->validated('name'),
            expectedPrice: (int) $this->validated('expected_price'),
            token: $this->validated('adoption_token'),
        );
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['dog_id' => __('Breed'), 'name' => __('Dog name')];
    }
}
