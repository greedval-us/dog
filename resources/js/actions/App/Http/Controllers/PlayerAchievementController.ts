import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:17
 * @route '/players/{user}/achievements'
 */
const PlayerAchievementController = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PlayerAchievementController.url(args, options),
    method: 'get',
})

PlayerAchievementController.definition = {
    methods: ["get","head"],
    url: '/players/{user}/achievements',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:17
 * @route '/players/{user}/achievements'
 */
PlayerAchievementController.url = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions) => {
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

    return PlayerAchievementController.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:17
 * @route '/players/{user}/achievements'
 */
PlayerAchievementController.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PlayerAchievementController.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:17
 * @route '/players/{user}/achievements'
 */
PlayerAchievementController.head = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: PlayerAchievementController.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:17
 * @route '/players/{user}/achievements'
 */
    const PlayerAchievementControllerForm = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: PlayerAchievementController.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:17
 * @route '/players/{user}/achievements'
 */
        PlayerAchievementControllerForm.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: PlayerAchievementController.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:17
 * @route '/players/{user}/achievements'
 */
        PlayerAchievementControllerForm.head = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: PlayerAchievementController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    PlayerAchievementController.form = PlayerAchievementControllerForm
export default PlayerAchievementController