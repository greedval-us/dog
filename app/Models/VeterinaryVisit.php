<?php

namespace App\Models;

use App\Modules\Pets\Enums\VeterinaryService;
use Carbon\CarbonImmutable;
use Database\Factories\VeterinaryVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $experience_awarded
 * @property int $pet_id
 * @property string $pet_name
 * @property VeterinaryService $service
 * @property int|null $disease_episode_id
 * @property array<string, string>|null $disease_name
 * @property string $token
 * @property int $price_paid
 * @property int|null $currency_transaction_id
 * @property CarbonImmutable $performed_at
 * @property CarbonImmutable|null $available_at
 */
#[Fillable(['user_id', 'pet_id', 'pet_name', 'service', 'disease_episode_id', 'disease_name', 'token', 'price_paid', 'currency_transaction_id', 'performed_at', 'available_at'])]
class VeterinaryVisit extends Model
{
    /** @use HasFactory<VeterinaryVisitFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'experience_awarded' => 'integer',
            'pet_id' => 'integer',
            'service' => VeterinaryService::class,
            'disease_episode_id' => 'integer',
            'disease_name' => 'array',
            'price_paid' => 'integer',
            'currency_transaction_id' => 'integer',
            'performed_at' => 'datetime',
            'available_at' => 'datetime',
        ];
    }
}
