<?php

namespace App\Modules\Pets\Queries;

use App\Models\InventoryItem;
use App\Models\PetCareAction;
use App\Models\User;
use App\Modules\Pets\Calculators\PetCareRules;
use App\Modules\Players\Enums\PlayerStatus;
use Illuminate\Support\Str;

final class GetPetCare
{
    public function __construct(private PetCareRules $rules) {}

    /** @return array<string, mixed> */
    public function handle(User $user, int $petId, string $locale): array
    {
        $pet = $user->pets()->findOrFail($petId);
        $options = $this->rules->options($pet->size);
        $items = $user->inventoryItems()->with('item.category')->where('remaining_uses', '>', 0)
            ->whereHas('item.category', fn ($query) => $query->whereIn('code', ['food', 'collars', 'leashes', 'toys', 'care']))
            ->oldest('id')->get();
        $cooldowns = PetCareAction::query()->where('pet_id', $petId)->where('available_at', '>', now())
            ->get()->groupBy('group')->map(fn ($actions) => $actions->max('available_at')->toIso8601String());
        $active = PetCareAction::query()->where('pet_id', $petId)->where('user_id', $user->id)
            ->where('activity_token', $pet->activity_token)->whereNull('completed_at')->first();

        $variants = [];

        foreach ($options as $id => $option) {
            $variants[] = [
                'id' => $id,
                ...$option,
                'reason' => $this->rules->unavailableReason($option, $pet->statePercentages(), $pet->energy),
            ];
        }

        return [
            'token' => (string) Str::uuid(),
            'serverNow' => now()->toIso8601String(),
            'blocked' => $user->status !== PlayerStatus::Active || $pet->retired_at !== null,
            'busy' => $pet->isBusy(),
            'cooldowns' => $cooldowns,
            'options' => $variants,
            'items' => $items->map(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'category' => $item->item->category->code,
                'name' => $item->name[$locale] ?? $item->name['en'] ?? $item->item->code,
                'quality' => $item->quality,
                'bonus' => $this->rules->qualityBonus($item->item->category->code, $item->quality),
                'remainingUses' => $item->remaining_uses,
            ])->all(),
            'active' => $active === null ? null : [
                'token' => $active->token,
                'label' => $options[$active->variant]['label'],
                'startedAt' => $active->created_at->toIso8601String(),
                'endsAt' => $active->ends_at->toIso8601String(),
                'effects' => $active->effects,
            ],
        ];
    }
}
