<?php

namespace App\Http\Requests;

use App\Models\Puppy;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PuppyOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $puppy = $this->route('puppy');

        return $this->user() instanceof User && $puppy instanceof Puppy && $puppy->user_id === $this->user()->id;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [];
    }
}
