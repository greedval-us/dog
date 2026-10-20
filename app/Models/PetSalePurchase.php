<?php

namespace App\Models;

use Database\Factories\PetSalePurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $listing_id
 * @property int $pet_id
 * @property int|null $buyer_id
 * @property int|null $seller_id
 * @property string $token
 * @property int $price_paid
 */
#[Fillable(['listing_id', 'pet_id', 'buyer_id', 'seller_id', 'token', 'price_paid'])]
class PetSalePurchase extends Model
{
    /** @use HasFactory<PetSalePurchaseFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['listing_id' => 'integer', 'pet_id' => 'integer', 'buyer_id' => 'integer', 'seller_id' => 'integer', 'price_paid' => 'integer'];
    }
}
