<?php

namespace App\MoonShine\Resources;

use App\Models\Dog;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
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
            $this->eventProfileField(),
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
            'event_profile' => ['sometimes', 'required', 'array:recognized_rkf,recognized_fci,fci_group,working_breed,height_ranges'],
            'event_profile.recognized_rkf' => ['required_with:event_profile', 'boolean'],
            'event_profile.recognized_fci' => ['required_with:event_profile', 'boolean'],
            'event_profile.fci_group' => ['nullable', 'integer', 'between:1,10'],
            'event_profile.working_breed' => ['required_with:event_profile', 'boolean'],
            'event_profile.height_ranges' => ['required_with:event_profile', 'array:male,female'],
            'event_profile.height_ranges.male' => ['required_with:event_profile', 'array', 'size:2'],
            'event_profile.height_ranges.female' => ['required_with:event_profile', 'array', 'size:2'],
            'event_profile.height_ranges.male.0' => ['required_with:event_profile', 'numeric', 'between:5,150'],
            'event_profile.height_ranges.male.1' => ['required_with:event_profile', 'numeric', 'between:5,150', 'gte:event_profile.height_ranges.male.0'],
            'event_profile.height_ranges.female.0' => ['required_with:event_profile', 'numeric', 'between:5,150'],
            'event_profile.height_ranges.female.1' => ['required_with:event_profile', 'numeric', 'between:5,150', 'gte:event_profile.height_ranges.female.0'],
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

    private function eventProfileField(): Json
    {
        return Json::make(__('admin.fields.event_profile'), 'event_profile')->fields([
            Switcher::make(__('admin.fields.recognized_rkf'), 'recognized_rkf'),
            Switcher::make(__('admin.fields.recognized_fci'), 'recognized_fci'),
            Number::make(__('admin.fields.fci_group'), 'fci_group')->min(1)->max(10)->nullable(),
            Switcher::make(__('admin.fields.working_breed'), 'working_breed'),
            Json::make(__('admin.fields.game_height_ranges'), 'height_ranges')->fields([
                Json::make(__('admin.fields.male'), 'male')->fields([
                    Number::make(__('admin.fields.minimum'), '0')->min(5)->max(150)->step(0.1)->required(),
                    Number::make(__('admin.fields.maximum'), '1')->min(5)->max(150)->step(0.1)->required(),
                ])->object(),
                Json::make(__('admin.fields.female'), 'female')->fields([
                    Number::make(__('admin.fields.minimum'), '0')->min(5)->max(150)->step(0.1)->required(),
                    Number::make(__('admin.fields.maximum'), '1')->min(5)->max(150)->step(0.1)->required(),
                ])->object(),
            ])->object(),
        ])->object()->onApply(static function (mixed $item, mixed $value): mixed {
            if ($item instanceof Dog && is_array($value)) {
                $item->event_profile = [
                    'recognized_rkf' => (bool) $value['recognized_rkf'], 'recognized_fci' => (bool) $value['recognized_fci'],
                    'fci_group' => isset($value['fci_group']) ? (int) $value['fci_group'] : null,
                    'working_breed' => (bool) $value['working_breed'],
                    'height_ranges' => [
                        'male' => array_map(floatval(...), array_values($value['height_ranges']['male'])),
                        'female' => array_map(floatval(...), array_values($value['height_ranges']['female'])),
                    ],
                ];
            }

            return $item;
        });
    }
}
