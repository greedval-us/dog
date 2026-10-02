<?php

namespace App\MoonShine\Resources;

use App\Models\Pet;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;

/** @extends AdminResource<Pet> */
#[Icon('heart')]
final class PetResource extends AdminResource
{
    protected string $model = Pet::class;

    protected string $titleKey = 'pets';

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('name'), $this->valueField('user_id'), $this->valueField('dog_id'), $this->valueField('generation'), $this->valueField('activity'), $this->valueField('activity_ends_at')];
    }

    protected function search(): array
    {
        return ['name', 'id'];
    }
}
