import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const form = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: form.url(args, options),
    method: 'get',
})

form.definition = {
    methods: ["get","head"],
    url: '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
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
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
form.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: form.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
form.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: form.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const formForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: form.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        formForm.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: form.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::form
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
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
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:191
 * @route '/admin/belongs-to-many-pivot/store/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const store = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/belongs-to-many-pivot/store/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["post"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:191
 * @route '/admin/belongs-to-many-pivot/store/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
store.url = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace('{resourceItem?}', parsedArgs.resourceItem?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:191
 * @route '/admin/belongs-to-many-pivot/store/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
store.post = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:191
 * @route '/admin/belongs-to-many-pivot/store/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const storeForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:191
 * @route '/admin/belongs-to-many-pivot/store/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        storeForm.post = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::update
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:224
 * @route '/admin/belongs-to-many-pivot/update/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const update = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/admin/belongs-to-many-pivot/update/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["put"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::update
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:224
 * @route '/admin/belongs-to-many-pivot/update/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
update.url = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace('{resourceItem?}', parsedArgs.resourceItem?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::update
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:224
 * @route '/admin/belongs-to-many-pivot/update/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
update.put = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::update
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:224
 * @route '/admin/belongs-to-many-pivot/update/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const updateForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::update
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:224
 * @route '/admin/belongs-to-many-pivot/update/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        updateForm.put = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::destroy
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:257
 * @route '/admin/belongs-to-many-pivot/destroy/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const destroy = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/belongs-to-many-pivot/destroy/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::destroy
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:257
 * @route '/admin/belongs-to-many-pivot/destroy/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
destroy.url = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace('{resourceItem?}', parsedArgs.resourceItem?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::destroy
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:257
 * @route '/admin/belongs-to-many-pivot/destroy/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
destroy.delete = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::destroy
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:257
 * @route '/admin/belongs-to-many-pivot/destroy/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const destroyForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::destroy
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:257
 * @route '/admin/belongs-to-many-pivot/destroy/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        destroyForm.delete = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const list = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(args, options),
    method: 'get',
})

list.definition = {
    methods: ["get","head"],
    url: '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
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
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
list.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
list.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: list.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const listForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: list.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        listForm.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: list.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::list
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
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
const belongsToManyPivot = {
    form: Object.assign(form, form),
store: Object.assign(store, store),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
list: Object.assign(list, list),
}

export default belongsToManyPivot