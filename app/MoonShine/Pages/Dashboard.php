<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Enums\StaffRole;
use App\MoonShine\Queries\DashboardMetrics;
use App\MoonShine\Resources\AdminAuditLogResource;
use App\MoonShine\Resources\CurrencyTransactionResource;
use App\MoonShine\Resources\ItemResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\PlayerResource;
use App\MoonShine\Support\StaffAccess;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\UI\Components\FlexibleRender;

#[SkipMenu]
class Dashboard extends Page
{
    public function getBreadcrumbs(): array
    {
        return ['#' => $this->getTitle()];
    }

    public function getTitle(): string
    {
        return __('admin.dashboard');
    }

    protected function components(): iterable
    {
        $role = StaffAccess::role();
        abort_unless($role instanceof StaffRole, 403);
        $validated = request()->validate(['days' => ['sometimes', 'integer', 'in:7,30,90']]);
        $days = (int) ($validated['days'] ?? 30);
        $sections = match ($role) {
            StaffRole::Administrator => [MoonShineUserResource::class, PlayerResource::class, CurrencyTransactionResource::class, ItemResource::class, AdminAuditLogResource::class],
            StaffRole::Analyst => [CurrencyTransactionResource::class, ItemResource::class],
            StaffRole::Moderator => [PlayerResource::class, AdminAuditLogResource::class],
        };
        $links = [];
        foreach ($sections as $class) {
            $resource = $this->getCore()->getContainer($class);
            $links[$resource->getTitle()] = $resource->getIndexPage()->getUrl();
        }

        return [
            FlexibleRender::make(view('moonshine.dashboard', [
                'role' => $role, 'days' => $days, 'links' => $links,
                'metrics' => $this->getCore()->getContainer(DashboardMetrics::class)->get($role, $days),
            ])),
        ];
    }
}
