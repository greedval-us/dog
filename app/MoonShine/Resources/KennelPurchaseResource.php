<?php

namespace App\MoonShine\Resources;

use App\Models\KennelPurchase;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;

/** @extends AdminResource<KennelPurchase> */
#[Icon('home')]
final class KennelPurchaseResource extends AdminResource
{
    protected string $model = KennelPurchase::class;

    protected string $titleKey = 'kennel_purchases';

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('user_id'), $this->valueField('pet_id'), $this->valueField('dog_id'), $this->valueField('price_paid'), $this->valueField('created_at')];
    }

    protected function search(): array
    {
        return ['id', 'token'];
    }
}
