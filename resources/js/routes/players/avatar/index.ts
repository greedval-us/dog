import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PlayerAvatarController::show
 * @see app/Http/Controllers/PlayerAvatarController.php:19
 * @route '/media/players/{user}/avatar'
 */
export const show = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/media/players/{user}/avatar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PlayerAvatarController::show
 * @see app/Http/Controllers/PlayerAvatarController.php:19
 * @route '/media/players/{user}/avatar'
 */
show.url = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlayerAvatarController::show
 * @see app/Http/Controllers/PlayerAvatarController.php:19
 * @route '/media/players/{user}/avatar'
 */
show.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PlayerAvatarController::show
 * @see app/Http/Controllers/PlayerAvatarController.php:19
 * @route '/media/players/{user}/avatar'
 */
show.head = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PlayerAvatarController::show
 * @see app/Http/Controllers/PlayerAvatarController.php:19
 * @route '/media/players/{user}/avatar'
 */
    const showForm = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PlayerAvatarController::show
 * @see app/Http/Controllers/PlayerAvatarController.php:19
 * @route '/media/players/{user}/avatar'
 */
        showForm.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PlayerAvatarController::show
 * @see app/Http/Controllers/PlayerAvatarController.php:19
 * @route '/media/players/{user}/avatar'
 */
        showForm.head = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\PlayerAvatarController::store
 * @see app/Http/Controllers/PlayerAvatarController.php:32
 * @route '/settings/avatar'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/avatar',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PlayerAvatarController::store
 * @see app/Http/Controllers/PlayerAvatarController.php:32
 * @route '/settings/avatar'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlayerAvatarController::store
 * @see app/Http/Controllers/PlayerAvatarController.php:32
 * @route '/settings/avatar'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PlayerAvatarController::store
 * @see app/Http/Controllers/PlayerAvatarController.php:32
 * @route '/settings/avatar'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PlayerAvatarController::store
 * @see app/Http/Controllers/PlayerAvatarController.php:32
 * @route '/settings/avatar'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\PlayerAvatarController::destroy
 * @see app/Http/Controllers/PlayerAvatarController.php:52
 * @route '/settings/avatar'
 */
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/avatar',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\PlayerAvatarController::destroy
 * @see app/Http/Controllers/PlayerAvatarController.php:52
 * @route '/settings/avatar'
 */
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlayerAvatarController::destroy
 * @see app/Http/Controllers/PlayerAvatarController.php:52
 * @route '/settings/avatar'
 */
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\PlayerAvatarController::destroy
 * @see app/Http/Controllers/PlayerAvatarController.php:52
 * @route '/settings/avatar'
 */
    const destroyForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PlayerAvatarController::destroy
 * @see app/Http/Controllers/PlayerAvatarController.php:52
 * @route '/settings/avatar'
 */
        destroyForm.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const avatar = {
    show: Object.assign(show, show),
store: Object.assign(store, store),
destroy: Object.assign(destroy, destroy),
}

export default avatar