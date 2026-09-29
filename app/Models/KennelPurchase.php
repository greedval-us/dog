<?php

namespace App\Models;

use Database\Factories\KennelPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $dog_id
 * @property int $pet_id
 * @property string $pet_name
 * @property string $token
 * @property int $price_paid
 * @property int|null $currency_transaction_id
 */
#[Fillable(['user_id', 'dog_id', 'pet_id', 'pet_name', 'token', 'price_paid', 'currency_transaction_id'])]
class KennelPurchase extends Model
{
    /** @use HasFactory<KennelPurchaseFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'dog_id' => 'integer',
            'pet_id' => 'integer',
            'price_paid' => 'integer',
            'currency_transaction_id' => 'integer',
        ];
    }
}
