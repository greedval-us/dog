<?php

namespace App\MoonShine\Resources;

use App\Models\Pet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;

/** @extends AdminResource<Pet> */
#[Icon('heart')]
final class PetResource extends AdminResource
{
    protected string $model = Pet::class;

    protected string $titleKey = 'pets';

    protected bool $editable = true;

    public function listFields(): array
    {
        return [ID::make()->sortable(), $this->valueField('name'), $this->valueField('user_id'), $this->valueField('dog_id'), $this->valueField('generation'), $this->valueField('activity'), $this->valueField('activity_ends_at')];
    }

    protected function search(): array
    {
        return ['name', 'id'];
    }

    public function editFields(): array
    {
        return [
            $this->valueField('name'),
            Json::make(__('admin.fields.event_profile'), 'event_profile')->fields([
                Number::make(__('admin.fields.height_cm'), 'height_cm')->min(5)->max(150)->step(0.1)->required(),
                Switcher::make(__('admin.fields.registered'), 'registered'),
                Switcher::make(__('admin.fields.pedigree_verified'), 'pedigree_verified'),
                Switcher::make(__('admin.fields.working_certificate_verified'), 'working_certificate_verified'),
                Select::make(__('admin.fields.race_category'), 'race_category')->options(['1' => '1', '2-RUS' => '2-RUS', '3' => '3', '4' => '4'])->required(),
            ])->object()->onApply(static function (mixed $item, mixed $value): mixed {
                if ($item instanceof Pet && is_array($value)) {
                    $item->event_profile = [
                        ...($item->event_profile ?? []),
                        'height_cm' => (float) $value['height_cm'], 'registered' => (bool) $value['registered'],
                        'pedigree_verified' => (bool) $value['pedigree_verified'],
                        'working_certificate_verified' => (bool) $value['working_certificate_verified'],
                        'race_category' => (string) $value['race_category'],
                    ];
                }

                return $item;
            }),
        ];
    }

    public function validationRules(Model $model): array
    {
        return [
            'event_profile' => ['required', 'array:height_cm,registered,pedigree_verified,working_certificate_verified,race_category'],
            'event_profile.height_cm' => ['required', 'numeric', 'between:5,150'],
            'event_profile.registered' => ['required', 'boolean'],
            'event_profile.pedigree_verified' => ['required', 'boolean'],
            'event_profile.working_certificate_verified' => ['required', 'boolean'],
            'event_profile.race_category' => ['required', Rule::in(['1', '2-RUS', '3', '4'])],
        ];
    }
}
