<?php

namespace App\MoonShine\Resources;

use App\Models\Dog;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;

/** @extends AdminResource<Dog> */
#[Icon('heart')]
final class DogResource extends AdminResource
{
    protected string $model = Dog::class;

    protected string $titleKey = 'breeds';

    protected string $column = 'breed';

    protected bool $editable = true;

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('breed'), $this->valueField('name'),
            $this->valueField('size'), $this->valueField('is_starter')];
    }

    public function editFields(): array
    {
        return [
            $this->valueField('breed'), $this->valueField('size'),
            $this->nameField(),
            Switcher::make(__('admin.fields.is_starter'), 'is_starter'),
            Number::make(__('admin.fields.endurance_potential'), 'endurance_potential')->min(1)->required(),
            Number::make(__('admin.fields.speed_potential'), 'speed_potential')->min(1)->required(),
            Number::make(__('admin.fields.strength_potential'), 'strength_potential')->min(1)->required(),
            Number::make(__('admin.fields.agility_potential'), 'agility_potential')->min(1)->required(),
            Number::make(__('admin.fields.obedience_potential'), 'obedience_potential')->min(1)->required(),
            Number::make(__('admin.fields.intelligence_potential'), 'intelligence_potential')->min(1)->required(),
            Number::make(__('admin.fields.health_max'), 'health_max')->min(1)->required(),
            Number::make(__('admin.fields.energy_max'), 'energy_max')->min(1)->required(),
            Number::make(__('admin.fields.satiety_max'), 'satiety_max')->min(1)->required(),
            Number::make(__('admin.fields.hydration_max'), 'hydration_max')->min(1)->required(),
            Number::make(__('admin.fields.mood_max'), 'mood_max')->min(1)->required(),
            Number::make(__('admin.fields.cleanliness_max'), 'cleanliness_max')->min(1)->required(),
            Number::make(__('admin.fields.bond_max'), 'bond_max')->min(1)->required(),
            Number::make(__('admin.fields.food_per_day'), 'food_per_day')->min(1)->required(),
            Number::make(__('admin.fields.water_per_day'), 'water_per_day')->min(1)->required(),
        ];
    }

    public function validationRules(Model $model): array
    {
        return [
            'name' => ['required', 'array:ru,en'],
            'name.ru' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'is_starter' => ['required', 'boolean'],
            'endurance_potential' => ['required', 'integer', 'between:1,2147483647'],
            'speed_potential' => ['required', 'integer', 'between:1,2147483647'],
            'strength_potential' => ['required', 'integer', 'between:1,2147483647'],
            'agility_potential' => ['required', 'integer', 'between:1,2147483647'],
            'obedience_potential' => ['required', 'integer', 'between:1,2147483647'],
            'intelligence_potential' => ['required', 'integer', 'between:1,2147483647'],
            'health_max' => ['required', 'integer', 'between:1,2147483647'],
            'energy_max' => ['required', 'integer', 'between:1,2147483647'],
            'satiety_max' => ['required', 'integer', 'between:1,2147483647'],
            'hydration_max' => ['required', 'integer', 'between:1,2147483647'],
            'mood_max' => ['required', 'integer', 'between:1,2147483647'],
            'cleanliness_max' => ['required', 'integer', 'between:1,2147483647'],
            'bond_max' => ['required', 'integer', 'between:1,2147483647'],
            'food_per_day' => ['required', 'integer', 'between:1,2147483647'],
            'water_per_day' => ['required', 'integer', 'between:1,2147483647'],
        ];
    }

    public function filterFields(): array
    {
        return [];
    }

    protected function search(): array
    {
        return ['id', 'breed'];
    }
}
