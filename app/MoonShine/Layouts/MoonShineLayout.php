<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Enums\StaffRole;
use App\MoonShine\Palettes\DogLivePalette;
use App\MoonShine\Resources\AdminAuditLogResource;
use App\MoonShine\Resources\CurrencyTransactionResource;
use App\MoonShine\Resources\DogResource;
use App\MoonShine\Resources\DogWorkShiftResource;
use App\MoonShine\Resources\DogWorkTypeResource;
use App\MoonShine\Resources\GameAssetResource;
use App\MoonShine\Resources\ItemPurchaseResource;
use App\MoonShine\Resources\ItemResource;
use App\MoonShine\Resources\KennelPurchaseResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\PetCareActionResource;
use App\MoonShine\Resources\PetResource;
use App\MoonShine\Resources\PlayerResource;
use App\MoonShine\Resources\ShopOfferResource;
use App\MoonShine\Resources\SkillResource;
use App\MoonShine\Resources\StatusEffectResource;
use App\MoonShine\Resources\TrainingResource;
use App\MoonShine\Support\StaffAccess;
use MoonShine\AssetManager\InlineCss;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;

final class MoonShineLayout extends AppLayout
{
    protected ?string $palette = DogLivePalette::class;

    protected function getFooterCopyright(): string
    {
        return '&copy; '.now()->year.' DogLive';
    }

    /** @return array<string, string> */
    protected function getFooterMenu(): array
    {
        return [];
    }

    protected function assets(): array
    {
        return [
            ...parent::assets(),
            InlineCss::make(<<<'CSS'
                .box, .card { border-radius: 20px; box-shadow: 0 5px 22px #42688008; }
                .btn, .form-input, .form-select, .form-textarea { border-radius: 12px; }
                .layout-header { background: var(--color-base-default); border-bottom: 1px solid var(--color-base-stroke); }
                .layout-menu { border-right: 1px solid var(--color-base-stroke); }
                .admin-dashboard { display: grid; gap: 24px; min-width: 0; }
                .admin-intro { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; padding: 28px; border-radius: 24px; border: 1px solid var(--color-base-stroke); background: linear-gradient(115deg, var(--color-base-default), var(--color-secondary)); }
                .admin-intro h2 { font-size: 26px; font-weight: 700; line-height: 1.3; }
                .admin-intro p { margin-top: 10px; max-width: 720px; line-height: 1.7; opacity: .8; }
                .admin-role { display: inline-block; margin-bottom: 12px; padding: 5px 12px; background: var(--color-base-default); border: 1px solid var(--color-base-stroke); border-radius: 30px; font-size: 12px; font-weight: 600; }
                .admin-period { display: flex; align-items: end; gap: 10px; flex-wrap: wrap; }
                .admin-period label { display: grid; gap: 6px; font-size: 13px; }
                .admin-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
                .admin-kpi { padding: 22px; border: 1px solid var(--color-base-stroke); background: var(--color-base-default); border-radius: 20px; }
                .admin-kpi dt { font-size: 13px; opacity: .8; }
                .admin-kpi dd { margin-top: 12px; font-size: 30px; font-weight: 700; font-variant-numeric: tabular-nums; }
                .admin-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
                .admin-panel { min-width: 0; padding: 24px; border: 1px solid var(--color-base-stroke); border-radius: 20px; background: var(--color-base-default); }
                .admin-panel h3 { font-size: 18px; font-weight: 700; margin-bottom: 18px; }
                .admin-muted { opacity: .75; line-height: 1.65; font-size: 13px; }
                .admin-section-title { font-size: 18px; font-weight: 700; margin-bottom: 16px; }
                .admin-links { display: flex; flex-wrap: wrap; gap: 10px; }
                .admin-links a { display: inline-flex; padding: 12px 16px; border-radius: 12px; border: 1px solid var(--color-base-stroke); font-weight: 600; font-size: 13px; text-decoration: none; }
                .admin-links a:hover { background: var(--color-secondary); }
                .admin-dashboard a:focus-visible, .admin-dashboard summary:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 4px; }
                .admin-chart { display: flex; align-items: end; gap: 1px; height: 150px; padding-top: 15px; }
                .admin-bar { flex: 1; min-width: 0; background: var(--color-primary); border-radius: 5px 5px 0 0; height: var(--bar-height); }
                .admin-chart-axis { display: flex; justify-content: space-between; margin-top: 10px; font-size: 12px; opacity: .75; }
                .admin-table-scroll { overflow-x: auto; max-height: 350px; }
                .admin-table-scroll table { width: 100%; border-collapse: collapse; font-size: 13px; }
                .admin-table-scroll th, .admin-table-scroll td { padding: 12px 10px; text-align: left; border-bottom: 1px solid var(--color-base-stroke); overflow-wrap: anywhere; }
                .admin-table-scroll caption { text-align: left; padding: 12px 0; font-weight: 600; }
                .admin-dashboard summary { cursor: pointer; padding: 14px 0; font-weight: 600; font-size: 13px; }
                .admin-economy { display: grid; gap: 12px; }
                .admin-economy > div { display: grid; grid-template-columns: 1fr auto auto; gap: 18px; padding: 14px 0; border-bottom: 1px solid var(--color-base-stroke); font-variant-numeric: tabular-nums; }
                .admin-log { display: grid; gap: 14px; }
                .admin-log li { padding-bottom: 14px; border-bottom: 1px solid var(--color-base-stroke); overflow-wrap: anywhere; }
                .admin-log small { display: block; margin-top: 5px; opacity: .75; }
                .admin-system { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; }
                .admin-system dt { font-size: 12px; opacity: .7; }
                .admin-system dd { font-weight: 600; margin-top: 5px; overflow-wrap: anywhere; }
                .admin-json { max-width: 100%; max-height: 600px; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 12px; }
                @media (max-width: 900px) { .admin-grid { grid-template-columns: minmax(0, 1fr); } }
                @media (max-width: 480px) { .admin-intro, .admin-panel { padding: 18px; } .admin-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .admin-kpi { padding: 16px; } .admin-kpi dd { font-size: 24px; } .admin-economy > div { gap: 8px; font-size: 12px; } }
                CSS),
        ];
    }

    protected function menu(): array
    {
        $role = StaffAccess::role();

        return [
            MenuItem::make(fn (): string => $this->getHomeUrl(), __('admin.dashboard'), 'squares-2x2'),
            MenuGroup::make(__('admin.team'), [
                MenuItem::make(MoonShineUserResource::class),
                MenuItem::make(MoonShineUserRoleResource::class),
            ])->canSee(fn (): bool => $role === StaffRole::Administrator),
            MenuGroup::make(__('admin.moderation'), [
                MenuItem::make(PlayerResource::class), MenuItem::make(PetResource::class), MenuItem::make(AdminAuditLogResource::class),
            ])->canSee(fn (): bool => in_array($role, [StaffRole::Administrator, StaffRole::Moderator], true)),
            MenuGroup::make(__('admin.analytics'), [
                MenuItem::make(CurrencyTransactionResource::class), MenuItem::make(ItemPurchaseResource::class),
                MenuItem::make(KennelPurchaseResource::class), MenuItem::make(PetCareActionResource::class), MenuItem::make(DogWorkShiftResource::class),
            ])->canSee(fn (): bool => in_array($role, [StaffRole::Administrator, StaffRole::Analyst], true)),
            MenuGroup::make(__('admin.catalogues'), [
                MenuItem::make(DogResource::class), MenuItem::make(ItemResource::class), MenuItem::make(ShopOfferResource::class),
                MenuItem::make(GameAssetResource::class), MenuItem::make(TrainingResource::class), MenuItem::make(SkillResource::class),
                MenuItem::make(DogWorkTypeResource::class), MenuItem::make(StatusEffectResource::class),
            ])->canSee(fn (): bool => in_array($role, [StaffRole::Administrator, StaffRole::Analyst], true)),
            MenuItem::make(route('home'), __('admin.open_site'), 'arrow-top-right-on-square')->blank(),
        ];
    }
}
