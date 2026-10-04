import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PuppyController::index
 * @see app/Http/Controllers/PuppyController.php:29
 * @route '/puppies'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/puppies',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PuppyController::index
 * @see app/Http/Controllers/PuppyController.php:29
 * @route '/puppies'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PuppyController::index
 * @see app/Http/Controllers/PuppyController.php:29
 * @route '/puppies'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PuppyController::index
 * @see app/Http/Controllers/PuppyController.php:29
 * @route '/puppies'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PuppyController::index
 * @see app/Http/Controllers/PuppyController.php:29
 * @route '/puppies'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PuppyController::index
 * @see app/Http/Controllers/PuppyController.php:29
 * @route '/puppies'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PuppyController::index
 * @see app/Http/Controllers/PuppyController.php:29
 * @route '/puppies'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\PuppyController::market
 * @see app/Http/Controllers/PuppyController.php:47
 * @route '/puppies/market'
 */
export const market = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: market.url(options),
    method: 'get',
})

market.definition = {
    methods: ["get","head"],
    url: '/puppies/market',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PuppyController::market
 * @see app/Http/Controllers/PuppyController.php:47
 * @route '/puppies/market'
 */
market.url = (options?: RouteQueryOptions) => {
    return market.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PuppyController::market
 * @see app/Http/Controllers/PuppyController.php:47
 * @route '/puppies/market'
 */
market.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: market.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PuppyController::market
 * @see app/Http/Controllers/PuppyController.php:47
 * @route '/puppies/market'
 */
market.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: market.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PuppyController::market
 * @see app/Http/Controllers/PuppyController.php:47
 * @route '/puppies/market'
 */
    const marketForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: market.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PuppyController::market
 * @see app/Http/Controllers/PuppyController.php:47
 * @route '/puppies/market'
 */
        marketForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: market.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PuppyController::market
 * @see app/Http/Controllers/PuppyController.php:47
 * @route '/puppies/market'
 */
        marketForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: market.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    market.form = marketForm
/**
* @see \App\Http\Controllers\PuppyController::keep
 * @see app/Http/Controllers/PuppyController.php:65
 * @route '/puppies/{puppy}/keep'
 */
export const keep = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: keep.url(args, options),
    method: 'post',
})

keep.definition = {
    methods: ["post"],
    url: '/puppies/{puppy}/keep',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PuppyController::keep
 * @see app/Http/Controllers/PuppyController.php:65
 * @route '/puppies/{puppy}/keep'
 */
keep.url = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { puppy: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { puppy: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    puppy: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        puppy: typeof args.puppy === 'object'
                ? args.puppy.id
                : args.puppy,
                }

    return keep.definition.url
            .replace('{puppy}', parsedArgs.puppy.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PuppyController::keep
 * @see app/Http/Controllers/PuppyController.php:65
 * @route '/puppies/{puppy}/keep'
 */
keep.post = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: keep.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PuppyController::keep
 * @see app/Http/Controllers/PuppyController.php:65
 * @route '/puppies/{puppy}/keep'
 */
    const keepForm = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: keep.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PuppyController::keep
 * @see app/Http/Controllers/PuppyController.php:65
 * @route '/puppies/{puppy}/keep'
 */
        keepForm.post = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: keep.url(args, options),
            method: 'post',
        })
    
    keep.form = keepForm
/**
* @see \App\Http\Controllers\PuppyController::list
 * @see app/Http/Controllers/PuppyController.php:77
 * @route '/puppies/{puppy}/listing'
 */
export const list = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: list.url(args, options),
    method: 'post',
})

list.definition = {
    methods: ["post"],
    url: '/puppies/{puppy}/listing',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PuppyController::list
 * @see app/Http/Controllers/PuppyController.php:77
 * @route '/puppies/{puppy}/listing'
 */
list.url = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { puppy: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { puppy: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    puppy: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        puppy: typeof args.puppy === 'object'
                ? args.puppy.id
                : args.puppy,
                }

    return list.definition.url
            .replace('{puppy}', parsedArgs.puppy.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PuppyController::list
 * @see app/Http/Controllers/PuppyController.php:77
 * @route '/puppies/{puppy}/listing'
 */
list.post = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: list.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PuppyController::list
 * @see app/Http/Controllers/PuppyController.php:77
 * @route '/puppies/{puppy}/listing'
 */
    const listForm = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: list.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PuppyController::list
 * @see app/Http/Controllers/PuppyController.php:77
 * @route '/puppies/{puppy}/listing'
 */
        listForm.post = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: list.url(args, options),
            method: 'post',
        })
    
    list.form = listForm
/**
* @see \App\Http\Controllers\PuppyController::unlist
 * @see app/Http/Controllers/PuppyController.php:88
 * @route '/puppies/{puppy}/listing'
 */
export const unlist = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: unlist.url(args, options),
    method: 'delete',
})

unlist.definition = {
    methods: ["delete"],
    url: '/puppies/{puppy}/listing',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\PuppyController::unlist
 * @see app/Http/Controllers/PuppyController.php:88
 * @route '/puppies/{puppy}/listing'
 */
unlist.url = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { puppy: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { puppy: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    puppy: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        puppy: typeof args.puppy === 'object'
                ? args.puppy.id
                : args.puppy,
                }

    return unlist.definition.url
            .replace('{puppy}', parsedArgs.puppy.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PuppyController::unlist
 * @see app/Http/Controllers/PuppyController.php:88
 * @route '/puppies/{puppy}/listing'
 */
unlist.delete = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: unlist.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\PuppyController::unlist
 * @see app/Http/Controllers/PuppyController.php:88
 * @route '/puppies/{puppy}/listing'
 */
    const unlistForm = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: unlist.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PuppyController::unlist
 * @see app/Http/Controllers/PuppyController.php:88
 * @route '/puppies/{puppy}/listing'
 */
        unlistForm.delete = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: unlist.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    unlist.form = unlistForm
/**
* @see \App\Http\Controllers\PuppyController::surrender
 * @see app/Http/Controllers/PuppyController.php:99
 * @route '/puppies/{puppy}/surrender'
 */
export const surrender = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: surrender.url(args, options),
    method: 'post',
})

surrender.definition = {
    methods: ["post"],
    url: '/puppies/{puppy}/surrender',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PuppyController::surrender
 * @see app/Http/Controllers/PuppyController.php:99
 * @route '/puppies/{puppy}/surrender'
 */
surrender.url = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { puppy: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { puppy: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    puppy: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        puppy: typeof args.puppy === 'object'
                ? args.puppy.id
                : args.puppy,
                }

    return surrender.definition.url
            .replace('{puppy}', parsedArgs.puppy.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PuppyController::surrender
 * @see app/Http/Controllers/PuppyController.php:99
 * @route '/puppies/{puppy}/surrender'
 */
surrender.post = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: surrender.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PuppyController::surrender
 * @see app/Http/Controllers/PuppyController.php:99
 * @route '/puppies/{puppy}/surrender'
 */
    const surrenderForm = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: surrender.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PuppyController::surrender
 * @see app/Http/Controllers/PuppyController.php:99
 * @route '/puppies/{puppy}/surrender'
 */
        surrenderForm.post = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: surrender.url(args, options),
            method: 'post',
        })
    
    surrender.form = surrenderForm
/**
* @see \App\Http\Controllers\PuppyController::purchase
 * @see app/Http/Controllers/PuppyController.php:110
 * @route '/puppies/{puppy}/purchase'
 */
export const purchase = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: purchase.url(args, options),
    method: 'post',
})

purchase.definition = {
    methods: ["post"],
    url: '/puppies/{puppy}/purchase',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PuppyController::purchase
 * @see app/Http/Controllers/PuppyController.php:110
 * @route '/puppies/{puppy}/purchase'
 */
purchase.url = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { puppy: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { puppy: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    puppy: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        puppy: typeof args.puppy === 'object'
                ? args.puppy.id
                : args.puppy,
                }

    return purchase.definition.url
            .replace('{puppy}', parsedArgs.puppy.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PuppyController::purchase
 * @see app/Http/Controllers/PuppyController.php:110
 * @route '/puppies/{puppy}/purchase'
 */
purchase.post = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: purchase.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PuppyController::purchase
 * @see app/Http/Controllers/PuppyController.php:110
 * @route '/puppies/{puppy}/purchase'
 */
    const purchaseForm = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: purchase.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PuppyController::purchase
 * @see app/Http/Controllers/PuppyController.php:110
 * @route '/puppies/{puppy}/purchase'
 */
        purchaseForm.post = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: purchase.url(args, options),
            method: 'post',
        })
    
    purchase.form = purchaseForm
const PuppyController = { index, market, keep, list, unlist, surrender, purchase }

export default PuppyController