import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PetMemorialController::index
 * @see app/Http/Controllers/PetMemorialController.php:15
 * @route '/players/{user}/memorial'
 */
export const index = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/players/{user}/memorial',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PetMemorialController::index
 * @see app/Http/Controllers/PetMemorialController.php:15
 * @route '/players/{user}/memorial'
 */
index.url = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { user: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'username' in args) {
            args = { user: args.username }
        }
    
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: typeof args.user === 'object'
                ? args.user.username
                : args.user,
                }

    return index.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetMemorialController::index
 * @see app/Http/Controllers/PetMemorialController.php:15
 * @route '/players/{user}/memorial'
 */
index.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PetMemorialController::index
 * @see app/Http/Controllers/PetMemorialController.php:15
 * @route '/players/{user}/memorial'
 */
index.head = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PetMemorialController::index
 * @see app/Http/Controllers/PetMemorialController.php:15
 * @route '/players/{user}/memorial'
 */
    const indexForm = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PetMemorialController::index
 * @see app/Http/Controllers/PetMemorialController.php:15
 * @route '/players/{user}/memorial'
 */
        indexForm.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PetMemorialController::index
 * @see app/Http/Controllers/PetMemorialController.php:15
 * @route '/players/{user}/memorial'
 */
        indexForm.head = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\PetMemorialController::show
 * @see app/Http/Controllers/PetMemorialController.php:26
 * @route '/players/{user}/memorial/{pet}'
 */
export const show = (args: { user: string | { username: string }, pet: number | { id: number } } | [user: string | { username: string }, pet: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/players/{user}/memorial/{pet}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PetMemorialController::show
 * @see app/Http/Controllers/PetMemorialController.php:26
 * @route '/players/{user}/memorial/{pet}'
 */
show.url = (args: { user: string | { username: string }, pet: number | { id: number } } | [user: string | { username: string }, pet: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    user: args[0],
                    pet: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        user: typeof args.user === 'object'
                ? args.user.username
                : args.user,
                                pet: typeof args.pet === 'object'
                ? args.pet.id
                : args.pet,
                }

    return show.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetMemorialController::show
 * @see app/Http/Controllers/PetMemorialController.php:26
 * @route '/players/{user}/memorial/{pet}'
 */
show.get = (args: { user: string | { username: string }, pet: number | { id: number } } | [user: string | { username: string }, pet: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PetMemorialController::show
 * @see app/Http/Controllers/PetMemorialController.php:26
 * @route '/players/{user}/memorial/{pet}'
 */
show.head = (args: { user: string | { username: string }, pet: number | { id: number } } | [user: string | { username: string }, pet: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PetMemorialController::show
 * @see app/Http/Controllers/PetMemorialController.php:26
 * @route '/players/{user}/memorial/{pet}'
 */
    const showForm = (args: { user: string | { username: string }, pet: number | { id: number } } | [user: string | { username: string }, pet: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PetMemorialController::show
 * @see app/Http/Controllers/PetMemorialController.php:26
 * @route '/players/{user}/memorial/{pet}'
 */
        showForm.get = (args: { user: string | { username: string }, pet: number | { id: number } } | [user: string | { username: string }, pet: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PetMemorialController::show
 * @see app/Http/Controllers/PetMemorialController.php:26
 * @route '/players/{user}/memorial/{pet}'
 */
        showForm.head = (args: { user: string | { username: string }, pet: number | { id: number } } | [user: string | { username: string }, pet: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const memorial = {
    index: Object.assign(index, index),
show: Object.assign(show, show),
}

export default memorial