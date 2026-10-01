<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Foundation\Http\FormRequest;

class StartDogWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && User::query()
            ->whereKey($this->user()->id)->where('status', PlayerStatus::Active)->exists();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['pet_id' => ['required', 'integer', 'min:1'], 'offer_id' => ['required', 'integer', 'min:1'],
            'token' => ['required', 'uuid']];
    }
}
