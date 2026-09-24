import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
const PageController45eb6a090c78a5d26be1c96001452797 = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PageController45eb6a090c78a5d26be1c96001452797.url(args, options),
    method: 'get',
})

PageController45eb6a090c78a5d26be1c96001452797.definition = {
    methods: ["get","head"],
    url: '/admin/page/{pageUri}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
PageController45eb6a090c78a5d26be1c96001452797.url = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { pageUri: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    pageUri: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        pageUri: args.pageUri,
                }

    return PageController45eb6a090c78a5d26be1c96001452797.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
PageController45eb6a090c78a5d26be1c96001452797.get = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PageController45eb6a090c78a5d26be1c96001452797.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
PageController45eb6a090c78a5d26be1c96001452797.head = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: PageController45eb6a090c78a5d26be1c96001452797.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
    const PageController45eb6a090c78a5d26be1c96001452797Form = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: PageController45eb6a090c78a5d26be1c96001452797.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
        PageController45eb6a090c78a5d26be1c96001452797Form.get = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: PageController45eb6a090c78a5d26be1c96001452797.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
        PageController45eb6a090c78a5d26be1c96001452797Form.head = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: PageController45eb6a090c78a5d26be1c96001452797.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    PageController45eb6a090c78a5d26be1c96001452797.form = PageController45eb6a090c78a5d26be1c96001452797Form
    /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
const PageControllerc2660645df8cfaaf90c88f73f7e8ae43 = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PageControllerc2660645df8cfaaf90c88f73f7e8ae43.url(args, options),
    method: 'get',
})

PageControllerc2660645df8cfaaf90c88f73f7e8ae43.definition = {
    methods: ["get","head"],
    url: '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
PageControllerc2660645df8cfaaf90c88f73f7e8ae43.url = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                    pageUri: args[1],
                    resourceItem: args[2],
                }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
            "resourceItem",
        ])

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                                pageUri: args.pageUri,
                                resourceItem: args.resourceItem,
                }

    return PageControllerc2660645df8cfaaf90c88f73f7e8ae43.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceItem?}', parsedArgs.resourceItem?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
PageControllerc2660645df8cfaaf90c88f73f7e8ae43.get = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PageControllerc2660645df8cfaaf90c88f73f7e8ae43.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
PageControllerc2660645df8cfaaf90c88f73f7e8ae43.head = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: PageControllerc2660645df8cfaaf90c88f73f7e8ae43.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
    const PageControllerc2660645df8cfaaf90c88f73f7e8ae43Form = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: PageControllerc2660645df8cfaaf90c88f73f7e8ae43.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
        PageControllerc2660645df8cfaaf90c88f73f7e8ae43Form.get = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: PageControllerc2660645df8cfaaf90c88f73f7e8ae43.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
        PageControllerc2660645df8cfaaf90c88f73f7e8ae43Form.head = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: PageControllerc2660645df8cfaaf90c88f73f7e8ae43.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    PageControllerc2660645df8cfaaf90c88f73f7e8ae43.form = PageControllerc2660645df8cfaaf90c88f73f7e8ae43Form

/**
* Multiple routes resolve to \MoonShine\Laravel\Http\Controllers\PageController::PageController, so this export is a
* dictionary keyed by URI rather than a callable. Call a specific route with `PageController['<uri>'](...)`,
* or import the route by name from your generated `routes/` directory.
*/
const PageController = {
    '/admin/page/{pageUri}': PageController45eb6a090c78a5d26be1c96001452797,
    '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}': PageControllerc2660645df8cfaaf90c88f73f7e8ae43,
}

export default PageController