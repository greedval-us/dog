import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const form = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: form.url(args, options),
    method: 'get',
})

form.definition = {
    methods: ["get","head"],
    url: '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
form.url = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
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

    return form.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace('{resourceItem?}', parsedArgs.resourceItem?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
form.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: form.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
form.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: form.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const formForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: form.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        formForm.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: form.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:32
 * @route '/admin/has-many/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        formForm.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: form.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    form.form = formForm
/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const list = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(args, options),
    method: 'get',
})

list.definition = {
    methods: ["get","head"],
    url: '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
list.url = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
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

    return list.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace('{resourceItem?}', parsedArgs.resourceItem?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
list.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
list.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: list.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const listForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: list.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        listForm.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: list.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HasManyController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HasManyController.php:160
 * @route '/admin/has-many/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        listForm.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: list.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    list.form = listForm
const hasMany = {
    form: Object.assign(form, form),
list: Object.assign(list, list),
}

export default hasMany