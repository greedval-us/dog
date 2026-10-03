<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Modules\Pets\Enums\VeterinaryService;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseVeterinaryServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User && User::query()
            ->whereKey($this->user()->id)->where('status', PlayerStatus::Active)->exists();
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
            'service' => ['required', Rule::enum(VeterinaryService::class)],
            'disease_episode_id' => ['nullable', 'required_if:service,treatment', 'prohibited_unless:service,treatment', 'integer', 'min:1'],
            'expected_price' => ['required', 'integer', 'min:1'],
            'token' => ['required', 'uuid'],
        ];
    }
}
