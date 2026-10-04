import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\GameEventController::index
 * @see app/Http/Controllers/GameEventController.php:26
 * @route '/game-events'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/game-events',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\GameEventController::index
 * @see app/Http/Controllers/GameEventController.php:26
 * @route '/game-events'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameEventController::index
 * @see app/Http/Controllers/GameEventController.php:26
 * @route '/game-events'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\GameEventController::index
 * @see app/Http/Controllers/GameEventController.php:26
 * @route '/game-events'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\GameEventController::index
 * @see app/Http/Controllers/GameEventController.php:26
 * @route '/game-events'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\GameEventController::index
 * @see app/Http/Controllers/GameEventController.php:26
 * @route '/game-events'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\GameEventController::index
 * @see app/Http/Controllers/GameEventController.php:26
 * @route '/game-events'
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
* @see \App\Http\Controllers\GameEventController::show
 * @see app/Http/Controllers/GameEventController.php:41
 * @route '/game-events/{gameEvent}'
 */
export const show = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/game-events/{gameEvent}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\GameEventController::show
 * @see app/Http/Controllers/GameEventController.php:41
 * @route '/game-events/{gameEvent}'
 */
show.url = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gameEvent: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { gameEvent: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    gameEvent: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        gameEvent: typeof args.gameEvent === 'object'
                ? args.gameEvent.id
                : args.gameEvent,
                }

    return show.definition.url
            .replace('{gameEvent}', parsedArgs.gameEvent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameEventController::show
 * @see app/Http/Controllers/GameEventController.php:41
 * @route '/game-events/{gameEvent}'
 */
show.get = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\GameEventController::show
 * @see app/Http/Controllers/GameEventController.php:41
 * @route '/game-events/{gameEvent}'
 */
show.head = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\GameEventController::show
 * @see app/Http/Controllers/GameEventController.php:41
 * @route '/game-events/{gameEvent}'
 */
    const showForm = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\GameEventController::show
 * @see app/Http/Controllers/GameEventController.php:41
 * @route '/game-events/{gameEvent}'
 */
        showForm.get = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\GameEventController::show
 * @see app/Http/Controllers/GameEventController.php:41
 * @route '/game-events/{gameEvent}'
 */
        showForm.head = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\GameEventController::register
 * @see app/Http/Controllers/GameEventController.php:59
 * @route '/game-events/{gameEvent}/register'
 */
export const register = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: register.url(args, options),
    method: 'post',
})

register.definition = {
    methods: ["post"],
    url: '/game-events/{gameEvent}/register',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\GameEventController::register
 * @see app/Http/Controllers/GameEventController.php:59
 * @route '/game-events/{gameEvent}/register'
 */
register.url = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gameEvent: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { gameEvent: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    gameEvent: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        gameEvent: typeof args.gameEvent === 'object'
                ? args.gameEvent.id
                : args.gameEvent,
                }

    return register.definition.url
            .replace('{gameEvent}', parsedArgs.gameEvent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameEventController::register
 * @see app/Http/Controllers/GameEventController.php:59
 * @route '/game-events/{gameEvent}/register'
 */
register.post = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: register.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\GameEventController::register
 * @see app/Http/Controllers/GameEventController.php:59
 * @route '/game-events/{gameEvent}/register'
 */
    const registerForm = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: register.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\GameEventController::register
 * @see app/Http/Controllers/GameEventController.php:59
 * @route '/game-events/{gameEvent}/register'
 */
        registerForm.post = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: register.url(args, options),
            method: 'post',
        })
    
    register.form = registerForm
/**
* @see \App\Http\Controllers\GameEventController::update
 * @see app/Http/Controllers/GameEventController.php:69
 * @route '/game-events/{gameEvent}/entry'
 */
export const update = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/game-events/{gameEvent}/entry',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\GameEventController::update
 * @see app/Http/Controllers/GameEventController.php:69
 * @route '/game-events/{gameEvent}/entry'
 */
update.url = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gameEvent: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { gameEvent: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    gameEvent: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        gameEvent: typeof args.gameEvent === 'object'
                ? args.gameEvent.id
                : args.gameEvent,
                }

    return update.definition.url
            .replace('{gameEvent}', parsedArgs.gameEvent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameEventController::update
 * @see app/Http/Controllers/GameEventController.php:69
 * @route '/game-events/{gameEvent}/entry'
 */
update.put = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\GameEventController::update
 * @see app/Http/Controllers/GameEventController.php:69
 * @route '/game-events/{gameEvent}/entry'
 */
    const updateForm = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\GameEventController::update
 * @see app/Http/Controllers/GameEventController.php:69
 * @route '/game-events/{gameEvent}/entry'
 */
        updateForm.put = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\GameEventController::cancel
 * @see app/Http/Controllers/GameEventController.php:79
 * @route '/game-events/{gameEvent}/cancel'
 */
export const cancel = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/game-events/{gameEvent}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\GameEventController::cancel
 * @see app/Http/Controllers/GameEventController.php:79
 * @route '/game-events/{gameEvent}/cancel'
 */
cancel.url = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gameEvent: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { gameEvent: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    gameEvent: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        gameEvent: typeof args.gameEvent === 'object'
                ? args.gameEvent.id
                : args.gameEvent,
                }

    return cancel.definition.url
            .replace('{gameEvent}', parsedArgs.gameEvent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameEventController::cancel
 * @see app/Http/Controllers/GameEventController.php:79
 * @route '/game-events/{gameEvent}/cancel'
 */
cancel.post = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\GameEventController::cancel
 * @see app/Http/Controllers/GameEventController.php:79
 * @route '/game-events/{gameEvent}/cancel'
 */
    const cancelForm = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancel.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\GameEventController::cancel
 * @see app/Http/Controllers/GameEventController.php:79
 * @route '/game-events/{gameEvent}/cancel'
 */
        cancelForm.post = (args: { gameEvent: number | { id: number } } | [gameEvent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancel.url(args, options),
            method: 'post',
        })
    
    cancel.form = cancelForm
const GameEventController = { index, show, register, update, cancel }

export default GameEventController