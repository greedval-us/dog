<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Modules\Pets\DTO\PurchasePetSlotData;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Foundation\Http\FormRequest;

class PurchasePetSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && User::query()
            ->whereKey($this->user()->id)->where('status', PlayerStatus::Active)->exists();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'slot' => ['required', 'integer', 'min:2', 'max:9'],
            'currency' => ['required', 'in:coins,gems'],
            'expected_price' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ];
    }

    public function toData(): PurchasePetSlotData
    {
        return new PurchasePetSlotData(
            $this->integer('slot'),
            $this->string('currency')->toString(),
            $this->integer('expected_price'),
        );
    }
}
