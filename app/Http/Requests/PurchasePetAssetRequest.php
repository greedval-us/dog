<?php

namespace App\Http\Requests;

use App\Modules\Appearance\DTO\PurchaseAssetData;
use App\Modules\Appearance\Enums\AssetCurrency;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class PurchasePetAssetRequest extends SelectPetAssetRequest
{
    /** @return array<string, list<string|Enum>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'expected_price' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'expected_currency' => ['required', Rule::enum(AssetCurrency::class)],
        ];
    }

    public function toData(): PurchaseAssetData
    {
        return new PurchaseAssetData(
            $this->integer('asset_id'),
            $this->integer('expected_price'),
            AssetCurrency::from($this->string('expected_currency')->toString()),
        );
    }
}
