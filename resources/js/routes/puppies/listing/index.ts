import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PuppyController::store
 * @see app/Http/Controllers/PuppyController.php:77
 * @route '/puppies/{puppy}/listing'
 */
export const store = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/puppies/{puppy}/listing',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PuppyController::store
 * @see app/Http/Controllers/PuppyController.php:77
 * @route '/puppies/{puppy}/listing'
 */
store.url = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{puppy}', parsedArgs.puppy.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PuppyController::store
 * @see app/Http/Controllers/PuppyController.php:77
 * @route '/puppies/{puppy}/listing'
 */
store.post = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PuppyController::store
 * @see app/Http/Controllers/PuppyController.php:77
 * @route '/puppies/{puppy}/listing'
 */
    const storeForm = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PuppyController::store
 * @see app/Http/Controllers/PuppyController.php:77
 * @route '/puppies/{puppy}/listing'
 */
        storeForm.post = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\PuppyController::destroy
 * @see app/Http/Controllers/PuppyController.php:88
 * @route '/puppies/{puppy}/listing'
 */
export const destroy = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/puppies/{puppy}/listing',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\PuppyController::destroy
 * @see app/Http/Controllers/PuppyController.php:88
 * @route '/puppies/{puppy}/listing'
 */
destroy.url = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{puppy}', parsedArgs.puppy.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PuppyController::destroy
 * @see app/Http/Controllers/PuppyController.php:88
 * @route '/puppies/{puppy}/listing'
 */
destroy.delete = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\PuppyController::destroy
 * @see app/Http/Controllers/PuppyController.php:88
 * @route '/puppies/{puppy}/listing'
 */
    const destroyForm = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PuppyController::destroy
 * @see app/Http/Controllers/PuppyController.php:88
 * @route '/puppies/{puppy}/listing'
 */
        destroyForm.delete = (args: { puppy: number | { id: number } } | [puppy: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const listing = {
    store: Object.assign(store, store),
destroy: Object.assign(destroy, destroy),
}

export default listing