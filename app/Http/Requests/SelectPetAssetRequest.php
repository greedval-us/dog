<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Foundation\Http\FormRequest;

class SelectPetAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && User::query()
            ->whereKey($this->user()->id)->where('status', PlayerStatus::Active)->exists();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['asset_id' => ['required', 'integer', 'min:1']];
    }
}
