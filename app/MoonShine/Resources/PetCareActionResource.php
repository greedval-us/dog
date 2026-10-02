<?php

namespace App\MoonShine\Resources;

use App\Models\PetCareAction;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;

/** @extends AdminResource<PetCareAction> */
#[Icon('sparkles')]
final class PetCareActionResource extends AdminResource
{
    protected string $model = PetCareAction::class;

    protected string $titleKey = 'care';

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('user_id'), $this->valueField('pet_id'), $this->valueField('group'), $this->valueField('variant'), $this->valueField('ends_at'), $this->valueField('completed_at')];
    }

    protected function search(): array
    {
        return ['id', 'token'];
    }
}
