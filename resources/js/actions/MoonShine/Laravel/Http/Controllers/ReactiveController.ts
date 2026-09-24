import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\ReactiveController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ReactiveController.php:18
 * @route '/admin/reactive/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
const ReactiveController = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ReactiveController.url(args, options),
    method: 'post',
})

ReactiveController.definition = {
    methods: ["post"],
    url: '/admin/reactive/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["post"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\ReactiveController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ReactiveController.php:18
 * @route '/admin/reactive/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
ReactiveController.url = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
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

    return ReactiveController.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace('{resourceItem?}', parsedArgs.resourceItem?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\ReactiveController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ReactiveController.php:18
 * @route '/admin/reactive/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
ReactiveController.post = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ReactiveController.url(args, options),
    method: 'post',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\ReactiveController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ReactiveController.php:18
 * @route '/admin/reactive/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const ReactiveControllerForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: ReactiveController.url(args, options),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\ReactiveController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ReactiveController.php:18
 * @route '/admin/reactive/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        ReactiveControllerForm.post = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: ReactiveController.url(args, options),
            method: 'post',
        })
    
    ReactiveController.form = ReactiveControllerForm
export default ReactiveController