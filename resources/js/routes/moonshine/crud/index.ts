import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::massDelete
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:142
 * @route '/admin/resource/{resourceUri}/crud'
 */
export const massDelete = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: massDelete.url(args, options),
    method: 'delete',
})

massDelete.definition = {
    methods: ["delete"],
    url: '/admin/resource/{resourceUri}/crud',
} satisfies RouteDefinition<["delete"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::massDelete
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:142
 * @route '/admin/resource/{resourceUri}/crud'
 */
massDelete.url = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { resourceUri: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                }

    return massDelete.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::massDelete
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:142
 * @route '/admin/resource/{resourceUri}/crud'
 */
massDelete.delete = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: massDelete.url(args, options),
    method: 'delete',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::massDelete
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:142
 * @route '/admin/resource/{resourceUri}/crud'
 */
    const massDeleteForm = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: massDelete.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::massDelete
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:142
 * @route '/admin/resource/{resourceUri}/crud'
 */
        massDeleteForm.delete = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: massDelete.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    massDelete.form = massDeleteForm
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::index
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:37
 * @route '/admin/resource/{resourceUri}/crud'
 */
export const index = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/resource/{resourceUri}/crud',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::index
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:37
 * @route '/admin/resource/{resourceUri}/crud'
 */
index.url = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { resourceUri: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                }

    return index.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::index
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:37
 * @route '/admin/resource/{resourceUri}/crud'
 */
index.get = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::index
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:37
 * @route '/admin/resource/{resourceUri}/crud'
 */
index.head = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::index
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:37
 * @route '/admin/resource/{resourceUri}/crud'
 */
    const indexForm = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::index
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:37
 * @route '/admin/resource/{resourceUri}/crud'
 */
        indexForm.get = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::index
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:37
 * @route '/admin/resource/{resourceUri}/crud'
 */
        indexForm.head = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::create
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/create'
 */
export const create = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/resource/{resourceUri}/crud/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::create
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/create'
 */
create.url = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { resourceUri: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                }

    return create.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::create
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/create'
 */
create.get = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::create
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/create'
 */
create.head = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::create
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/create'
 */
    const createForm = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::create
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/create'
 */
        createForm.get = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::create
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/create'
 */
        createForm.head = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    create.form = createForm
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:85
 * @route '/admin/resource/{resourceUri}/crud'
 */
export const store = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/resource/{resourceUri}/crud',
} satisfies RouteDefinition<["post"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:85
 * @route '/admin/resource/{resourceUri}/crud'
 */
store.url = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { resourceUri: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                }

    return store.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:85
 * @route '/admin/resource/{resourceUri}/crud'
 */
store.post = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:85
 * @route '/admin/resource/{resourceUri}/crud'
 */
    const storeForm = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:85
 * @route '/admin/resource/{resourceUri}/crud'
 */
        storeForm.post = (args: { resourceUri: string | number } | [resourceUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::show
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:60
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
export const show = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/admin/resource/{resourceUri}/crud/{resourceItem}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::show
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:60
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
show.url = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                    resourceItem: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                                resourceItem: args.resourceItem,
                }

    return show.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace('{resourceItem}', parsedArgs.resourceItem.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::show
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:60
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
show.get = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::show
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:60
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
show.head = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::show
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:60
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
    const showForm = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::show
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:60
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
        showForm.get = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::show
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:60
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
        showForm.head = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::edit
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}/edit'
 */
export const edit = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/resource/{resourceUri}/crud/{resourceItem}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::edit
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}/edit'
 */
edit.url = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                    resourceItem: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                                resourceItem: args.resourceItem,
                }

    return edit.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace('{resourceItem}', parsedArgs.resourceItem.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::edit
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}/edit'
 */
edit.get = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::edit
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}/edit'
 */
edit.head = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::edit
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}/edit'
 */
    const editForm = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::edit
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}/edit'
 */
        editForm.get = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::edit
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:0
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}/edit'
 */
        editForm.head = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    edit.form = editForm
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::update
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:95
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
export const update = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/admin/resource/{resourceUri}/crud/{resourceItem}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::update
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:95
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
update.url = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                    resourceItem: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                                resourceItem: args.resourceItem,
                }

    return update.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace('{resourceItem}', parsedArgs.resourceItem.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::update
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:95
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
update.put = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::update
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:95
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
update.patch = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::update
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:95
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
    const updateForm = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::update
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:95
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
        updateForm.put = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::update
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:95
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
        updateForm.patch = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::destroy
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:101
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
export const destroy = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/resource/{resourceUri}/crud/{resourceItem}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::destroy
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:101
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
destroy.url = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                    resourceItem: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                                resourceItem: args.resourceItem,
                }

    return destroy.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace('{resourceItem}', parsedArgs.resourceItem.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::destroy
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:101
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
destroy.delete = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::destroy
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:101
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
    const destroyForm = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\CrudController::destroy
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/CrudController.php:101
 * @route '/admin/resource/{resourceUri}/crud/{resourceItem}'
 */
        destroyForm.delete = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const crud = {
    massDelete: Object.assign(massDelete, massDelete),
index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
show: Object.assign(show, show),
edit: Object.assign(edit, edit),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
}

export default crud