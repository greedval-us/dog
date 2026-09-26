import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
import avatar from './avatar'
/**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
 */
export const show = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/players/{user}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
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
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
 */
show.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
 */
show.head = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
 */
    const showForm = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
 */
        showForm.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
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
const players = {
    avatar: Object.assign(avatar, avatar),
show: Object.assign(show, show),
}

export default players