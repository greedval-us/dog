import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
const HandlerController = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HandlerController.url(args, options),
    method: 'get',
})

HandlerController.definition = {
    methods: ["get","head","post","put","patch","delete","options"],
    url: '/admin/resource/{resourceUri}/handler/{handlerUri}',
} satisfies RouteDefinition<["get","head","post","put","patch","delete","options"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
HandlerController.url = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                    handlerUri: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                                handlerUri: args.handlerUri,
                }

    return HandlerController.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace('{handlerUri}', parsedArgs.handlerUri.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
HandlerController.get = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HandlerController.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
HandlerController.head = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: HandlerController.url(args, options),
    method: 'head',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
HandlerController.post = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: HandlerController.url(args, options),
    method: 'post',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
HandlerController.put = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: HandlerController.url(args, options),
    method: 'put',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
HandlerController.patch = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: HandlerController.url(args, options),
    method: 'patch',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
HandlerController.delete = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: HandlerController.url(args, options),
    method: 'delete',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
HandlerController.options = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'options'> => ({
    url: HandlerController.url(args, options),
    method: 'options',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
    const HandlerControllerForm = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: HandlerController.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        HandlerControllerForm.get = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: HandlerController.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        HandlerControllerForm.head = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: HandlerController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        HandlerControllerForm.post = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: HandlerController.url(args, options),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        HandlerControllerForm.put = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: HandlerController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        HandlerControllerForm.patch = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: HandlerController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        HandlerControllerForm.delete = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: HandlerController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        HandlerControllerForm.options = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: HandlerController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'OPTIONS',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    HandlerController.form = HandlerControllerForm
export default HandlerController