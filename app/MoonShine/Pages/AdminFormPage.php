<?php

namespace App\MoonShine\Pages;

use App\MoonShine\Resources\AdminResource;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;

/** @extends FormPage<AdminResource, Model> */
final class AdminFormPage extends FormPage
{
    protected function fields(): iterable
    {
        return [Box::make($this->getResource()->editFields())];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return $this->getResource()->validationRules($item->getOriginal());
    }
}
