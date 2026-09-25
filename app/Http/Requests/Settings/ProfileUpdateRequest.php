<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Data\UpdatePlayerProfileData;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    public function toData(): UpdatePlayerProfileData
    {
        $validated = $this->validated();

        return new UpdatePlayerProfileData(
            name: $validated['name'],
            email: $validated['email'],
            bio: $validated['bio'] ?? null,
            bioProvided: array_key_exists('bio', $validated),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
