import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const formComponent = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: formComponent.url(args, options),
    method: 'get',
})

formComponent.definition = {
    methods: ["get","head"],
    url: '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
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
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
formComponent.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: formComponent.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
formComponent.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: formComponent.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const formComponentForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: formComponent.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        formComponentForm.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: formComponent.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::formComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:35
 * @route '/admin/belongs-to-many-pivot/form/{pageUri}/{resourceUri?}/{resourceItem?}'
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
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const listComponent = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: listComponent.url(args, options),
    method: 'get',
})

listComponent.definition = {
    methods: ["get","head"],
    url: '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
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
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
listComponent.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: listComponent.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
listComponent.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: listComponent.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const listComponentForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: listComponent.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        listComponentForm.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: listComponent.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\BelongsToManyPivotController::listComponent
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/BelongsToManyPivotController.php:288
 * @route '/admin/belongs-to-many-pivot/list/{pageUri}/{resourceUri?}/{resourceItem?}'
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
const BelongsToManyPivotController = { formComponent, store, update, destroy, listComponent }

export default BelongsToManyPivotController