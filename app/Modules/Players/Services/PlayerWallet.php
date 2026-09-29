<?php

namespace App\Modules\Players\Services;

use App\Models\CurrencyTransaction;
use App\Models\User;
use App\Modules\Players\Exceptions\InsufficientFunds;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Public cross-module operation for balance changes and their durable ledger entries. */
final class PlayerWallet
{
    /**
     * Use a stable, server-generated operation key per reward or purchase.
     * Call inside the gameplay transaction to commit the balance and its result together.
     * Operations touching multiple players must lock their user rows in ID order first.
     */
    public function change(User $user, string $currency, int $amount, string $operationKey, string $reason): CurrencyTransaction
    {
        if (! in_array($currency, ['coins', 'gems'], true) || $amount === 0 || $amount === PHP_INT_MIN
            || $operationKey === '' || strlen($operationKey) > 128 || $reason === '' || strlen($reason) > 64) {
            throw new InvalidArgumentException('Invalid wallet operation.');
        }

        return DB::transaction(function () use ($user, $currency, $amount, $operationKey, $reason): CurrencyTransaction {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $existing = CurrencyTransaction::query()->whereBelongsTo($owner)->where('operation_key', $operationKey)->first();

            if ($existing !== null) {
                if ($existing->currency !== $currency || $existing->amount !== $amount || $existing->reason !== $reason) {
                    throw new InvalidArgumentException('The operation key was already used for a different wallet operation.');
                }

                return $existing;
            }

            $before = (int) $owner->getAttribute($currency);

            if ($amount < 0 && $before < -$amount) {
                throw new InsufficientFunds;
            }

            if ($before < 0 || ($amount > 0 && $before > PHP_INT_MAX - $amount)) {
                throw new InvalidArgumentException('The wallet balance is outside its supported range.');
            }

            $after = $before + $amount;
            $changed = User::query()->whereKey($owner->id)->where($currency, $before)->update([$currency => $after]);

            if ($changed !== 1) {
                throw new InsufficientFunds;
            }

            return CurrencyTransaction::query()->create([
                'user_id' => $owner->id,
                'currency' => $currency,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'operation_key' => $operationKey,
                'reason' => $reason,
            ]);
        }, attempts: 3);
    }
}
