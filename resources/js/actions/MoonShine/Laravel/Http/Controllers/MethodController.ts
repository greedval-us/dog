import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
const MethodController = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MethodController.url(args, options),
    method: 'get',
})

MethodController.definition = {
    methods: ["get","head","post","put","patch","delete","options"],
    url: '/admin/method/{pageUri}/{resourceUri?}',
} satisfies RouteDefinition<["get","head","post","put","patch","delete","options"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
MethodController.url = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    pageUri: args[0],
                    resourceUri: args[1],
                }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
            "resourceUri",
        ])

    const parsedArgs = {
                        pageUri: args.pageUri,
                                resourceUri: args.resourceUri,
                }

    return MethodController.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
MethodController.get = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: MethodController.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
MethodController.head = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: MethodController.url(args, options),
    method: 'head',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
MethodController.post = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: MethodController.url(args, options),
    method: 'post',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
MethodController.put = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: MethodController.url(args, options),
    method: 'put',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
MethodController.patch = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: MethodController.url(args, options),
    method: 'patch',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
MethodController.delete = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: MethodController.url(args, options),
    method: 'delete',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
MethodController.options = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'options'> => ({
    url: MethodController.url(args, options),
    method: 'options',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
    const MethodControllerForm = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: MethodController.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        MethodControllerForm.get = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: MethodController.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        MethodControllerForm.head = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: MethodController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        MethodControllerForm.post = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: MethodController.url(args, options),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        MethodControllerForm.put = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: MethodController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        MethodControllerForm.patch = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: MethodController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        MethodControllerForm.delete = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: MethodController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        MethodControllerForm.options = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: MethodController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'OPTIONS',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    MethodController.form = MethodControllerForm
export default MethodController