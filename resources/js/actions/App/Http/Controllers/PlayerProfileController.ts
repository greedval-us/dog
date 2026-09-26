import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
 */
const PlayerProfileController = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PlayerProfileController.url(args, options),
    method: 'get',
})

PlayerProfileController.definition = {
    methods: ["get","head"],
    url: '/players/{user}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
 */
PlayerProfileController.url = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions) => {
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

    return PlayerProfileController.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
 */
PlayerProfileController.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PlayerProfileController.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
 */
PlayerProfileController.head = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: PlayerProfileController.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
 */
    const PlayerProfileControllerForm = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: PlayerProfileController.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
 */
        PlayerProfileControllerForm.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: PlayerProfileController.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:14
 * @route '/players/{user}'
 */
        PlayerProfileControllerForm.head = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: PlayerProfileController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    PlayerProfileController.form = PlayerProfileControllerForm
export default PlayerProfileController