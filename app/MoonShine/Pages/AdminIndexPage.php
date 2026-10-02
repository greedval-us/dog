<?php

namespace App\MoonShine\Pages;

use App\MoonShine\Resources\AdminResource;
use MoonShine\Laravel\Pages\Crud\IndexPage;

/** @extends IndexPage<AdminResource> */
final class AdminIndexPage extends IndexPage
{
    protected function fields(): iterable
    {
        return $this->getResource()->listFields();
    }

    protected function filters(): iterable
    {
        return $this->getResource()->filterFields();
    }
}
