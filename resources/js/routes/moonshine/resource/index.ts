import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
export const page = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: page.url(args, options),
    method: 'get',
})

page.definition = {
    methods: ["get","head"],
    url: '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
page.url = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
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

    return page.definition.url
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
page.get = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: page.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
page.head = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: page.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
    const pageForm = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: page.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
        pageForm.get = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: page.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/resource/{resourceUri}/{pageUri}/{resourceItem?}'
 */
        pageForm.head = (args: { resourceUri: string | number, pageUri: string | number, resourceItem?: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: page.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    page.form = pageForm
const resource = {
    page: Object.assign(page, page),
}

export default resource