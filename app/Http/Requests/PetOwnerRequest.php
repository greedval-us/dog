<?php

namespace App\Http\Requests;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PetOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pet = $this->route('pet');

        return $this->user() instanceof User && $pet instanceof Pet && $pet->user_id === $this->user()->id;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [];
    }
}
