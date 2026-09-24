import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
const AsyncSearchController = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AsyncSearchController.url(args, options),
    method: 'get',
})

AsyncSearchController.definition = {
    methods: ["get","head"],
    url: '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
AsyncSearchController.url = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    pageUri: args[0],
                    resourceUri: args[1],
                    resourceItem: args[2],
                }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
            "resourceUri",
            "resourceItem",
        ])

    const parsedArgs = {
                        pageUri: args.pageUri,
                                resourceUri: args.resourceUri,
                                resourceItem: args.resourceItem,
                }

    return AsyncSearchController.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace('{resourceItem?}', parsedArgs.resourceItem?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
AsyncSearchController.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AsyncSearchController.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
AsyncSearchController.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AsyncSearchController.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const AsyncSearchControllerForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: AsyncSearchController.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        AsyncSearchControllerForm.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: AsyncSearchController.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        AsyncSearchControllerForm.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: AsyncSearchController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    AsyncSearchController.form = AsyncSearchControllerForm
export default AsyncSearchController