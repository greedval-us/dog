<?php

namespace App\MoonShine\Pages;

use App\MoonShine\Resources\AdminResource;
use MoonShine\Laravel\Pages\Crud\DetailPage;

/** @extends DetailPage<AdminResource> */
final class AdminDetailPage extends DetailPage
{
    protected function fields(): iterable
    {
        return $this->getResource()->viewFields();
    }
}
