import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\SystemNotificationController::index
 * @see app/Http/Controllers/SystemNotificationController.php:13
 * @route '/notifications'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/notifications',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SystemNotificationController::index
 * @see app/Http/Controllers/SystemNotificationController.php:13
 * @route '/notifications'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SystemNotificationController::index
 * @see app/Http/Controllers/SystemNotificationController.php:13
 * @route '/notifications'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\SystemNotificationController::index
 * @see app/Http/Controllers/SystemNotificationController.php:13
 * @route '/notifications'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\SystemNotificationController::index
 * @see app/Http/Controllers/SystemNotificationController.php:13
 * @route '/notifications'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\SystemNotificationController::index
 * @see app/Http/Controllers/SystemNotificationController.php:13
 * @route '/notifications'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\SystemNotificationController::index
 * @see app/Http/Controllers/SystemNotificationController.php:13
 * @route '/notifications'
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
* @see \App\Http\Controllers\SystemNotificationController::markAllRead
 * @see app/Http/Controllers/SystemNotificationController.php:51
 * @route '/notifications/read-all'
 */
export const markAllRead = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: markAllRead.url(options),
    method: 'patch',
})

markAllRead.definition = {
    methods: ["patch"],
    url: '/notifications/read-all',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\SystemNotificationController::markAllRead
 * @see app/Http/Controllers/SystemNotificationController.php:51
 * @route '/notifications/read-all'
 */
markAllRead.url = (options?: RouteQueryOptions) => {
    return markAllRead.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SystemNotificationController::markAllRead
 * @see app/Http/Controllers/SystemNotificationController.php:51
 * @route '/notifications/read-all'
 */
markAllRead.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: markAllRead.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\SystemNotificationController::markAllRead
 * @see app/Http/Controllers/SystemNotificationController.php:51
 * @route '/notifications/read-all'
 */
    const markAllReadForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: markAllRead.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\SystemNotificationController::markAllRead
 * @see app/Http/Controllers/SystemNotificationController.php:51
 * @route '/notifications/read-all'
 */
        markAllReadForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: markAllRead.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    markAllRead.form = markAllReadForm
/**
* @see \App\Http\Controllers\SystemNotificationController::update
 * @see app/Http/Controllers/SystemNotificationController.php:38
 * @route '/notifications/{notification}'
 */
export const update = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/notifications/{notification}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\SystemNotificationController::update
 * @see app/Http/Controllers/SystemNotificationController.php:38
 * @route '/notifications/{notification}'
 */
update.url = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { notification: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    notification: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        notification: args.notification,
                }

    return update.definition.url
            .replace('{notification}', parsedArgs.notification.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SystemNotificationController::update
 * @see app/Http/Controllers/SystemNotificationController.php:38
 * @route '/notifications/{notification}'
 */
update.patch = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\SystemNotificationController::update
 * @see app/Http/Controllers/SystemNotificationController.php:38
 * @route '/notifications/{notification}'
 */
    const updateForm = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\SystemNotificationController::update
 * @see app/Http/Controllers/SystemNotificationController.php:38
 * @route '/notifications/{notification}'
 */
        updateForm.patch = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const SystemNotificationController = { index, markAllRead, update }

export default SystemNotificationController