import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const formComponent = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: formComponent.url(args, options),
    method: 'get',
})

formComponent.definition = {
    methods: ["get","head"],
    url: '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
formComponent.url = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
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

    return formComponent.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace('{resourceItem?}', parsedArgs.resourceItem?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
formComponent.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: formComponent.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
formComponent.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: formComponent.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const formComponentForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: formComponent.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        formComponentForm.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: formComponent.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        formComponentForm.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: formComponent.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    formComponent.form = formComponentForm
/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const listComponent = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: listComponent.url(args, options),
    method: 'get',
})

listComponent.definition = {
    methods: ["get","head"],
    url: '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
listComponent.url = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
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

    return listComponent.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace('{resourceItem?}', parsedArgs.resourceItem?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
listComponent.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: listComponent.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
listComponent.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: listComponent.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const listComponentForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: listComponent.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        listComponentForm.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: listComponent.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        listComponentForm.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: listComponent.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    listComponent.form = listComponentForm
const HasManyController = { formComponent, listComponent }

export default HasManyController