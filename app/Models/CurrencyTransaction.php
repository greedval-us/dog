<?php

namespace App\Models;

use Database\Factories\CurrencyTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $currency
 * @property int $amount
 * @property int $balance_before
 * @property int $balance_after
 * @property string $operation_key
 * @property string $reason
 */
#[Fillable(['user_id', 'currency', 'amount', 'balance_before', 'balance_after', 'operation_key', 'reason'])]
class CurrencyTransaction extends Model
{
    /** @use HasFactory<CurrencyTransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_before' => 'integer', 'balance_after' => 'integer'];
    }
}
