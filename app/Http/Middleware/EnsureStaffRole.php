<?php

namespace App\Http\Middleware;

use App\MoonShine\Support\StaffAccess;
use Closure;
use Illuminate\Http\Request;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\PageType;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffRole
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(StaffAccess::role() !== null, 403);

        if ($request->route('resourceUri') !== null) {
            $resource = moonshineRequest()->getResource();
            abort_unless($resource instanceof ModelResource, 404);
            $ability = match ($request->route()->getName()) {
                'moonshine.crud.create', 'moonshine.crud.store' => Ability::CREATE,
                'moonshine.crud.edit', 'moonshine.crud.update', 'moonshine.update-field.through-column', 'moonshine.update-field.through-relation' => Ability::UPDATE,
                'moonshine.crud.destroy' => Ability::DELETE,
                'moonshine.crud.massDelete' => Ability::MASS_DELETE,
                'moonshine.crud.show' => Ability::VIEW,
                'moonshine.resource.page' => match (moonshineRequest()->getPage()->getPageType()) {
                    PageType::FORM => $request->route('resourceItem') === null ? Ability::CREATE : Ability::UPDATE,
                    PageType::DETAIL => Ability::VIEW,
                    default => Ability::VIEW_ANY,
                },
                default => Ability::VIEW_ANY,
            };
            abort_unless(StaffAccess::allows($resource->getModel()::class, $ability), 403);
        }

        return $next($request);
    }
}
