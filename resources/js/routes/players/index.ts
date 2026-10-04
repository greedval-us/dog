import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
import avatar from './avatar'
import memorial from './memorial'
/**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:18
 * @route '/players/{user}/achievements'
 */
export const achievements = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: achievements.url(args, options),
    method: 'get',
})

achievements.definition = {
    methods: ["get","head"],
    url: '/players/{user}/achievements',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:18
 * @route '/players/{user}/achievements'
 */
achievements.url = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions) => {
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

    return achievements.definition.url
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:18
 * @route '/players/{user}/achievements'
 */
achievements.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: achievements.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:18
 * @route '/players/{user}/achievements'
 */
achievements.head = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: achievements.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:18
 * @route '/players/{user}/achievements'
 */
    const achievementsForm = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: achievements.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:18
 * @route '/players/{user}/achievements'
 */
        achievementsForm.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: achievements.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PlayerAchievementController::__invoke
 * @see app/Http/Controllers/PlayerAchievementController.php:18
 * @route '/players/{user}/achievements'
 */
        achievementsForm.head = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: achievements.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    achievements.form = achievementsForm
/**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:16
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
 * @see app/Http/Controllers/PlayerProfileController.php:16
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
 * @see app/Http/Controllers/PlayerProfileController.php:16
 * @route '/players/{user}'
 */
show.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:16
 * @route '/players/{user}'
 */
show.head = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:16
 * @route '/players/{user}'
 */
    const showForm = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:16
 * @route '/players/{user}'
 */
        showForm.get = (args: { user: string | { username: string } } | [user: string | { username: string } ] | string | { username: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PlayerProfileController::__invoke
 * @see app/Http/Controllers/PlayerProfileController.php:16
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
achievements: Object.assign(achievements, achievements),
show: Object.assign(show, show),
memorial: Object.assign(memorial, memorial),
}

export default players