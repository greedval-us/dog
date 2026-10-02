<?php

namespace App\MoonShine\Resources;

use App\Models\DogWorkShift;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;

/** @extends AdminResource<DogWorkShift> */
#[Icon('briefcase')]
final class DogWorkShiftResource extends AdminResource
{
    protected string $model = DogWorkShift::class;

    protected string $titleKey = 'shifts';

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('user_id'), $this->valueField('pet_id'), $this->valueField('pet_name'), $this->valueField('coins_reward'), $this->valueField('gems_reward'), $this->valueField('ends_at'), $this->valueField('completed_at')];
    }

    protected function search(): array
    {
        return ['id', 'token'];
    }
}
