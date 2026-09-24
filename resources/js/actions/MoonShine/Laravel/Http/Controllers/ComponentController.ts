import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
const ComponentController = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ComponentController.url(args, options),
    method: 'get',
})

ComponentController.definition = {
    methods: ["get","head"],
    url: '/admin/component/{pageUri}/{resourceUri?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
ComponentController.url = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions) => {
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

    return ComponentController.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
ComponentController.get = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ComponentController.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
ComponentController.head = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ComponentController.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
    const ComponentControllerForm = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: ComponentController.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
        ComponentControllerForm.get = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: ComponentController.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
        ComponentControllerForm.head = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: ComponentController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    ComponentController.form = ComponentControllerForm
export default ComponentController