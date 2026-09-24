import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\NotificationController::readAll
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/NotificationController.php:11
 * @route '/admin/notifications'
 */
export const readAll = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: readAll.url(options),
    method: 'post',
})

readAll.definition = {
    methods: ["post"],
    url: '/admin/notifications',
} satisfies RouteDefinition<["post"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\NotificationController::readAll
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/NotificationController.php:11
 * @route '/admin/notifications'
 */
readAll.url = (options?: RouteQueryOptions) => {
    return readAll.definition.url + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\NotificationController::readAll
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/NotificationController.php:11
 * @route '/admin/notifications'
 */
readAll.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: readAll.url(options),
    method: 'post',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\NotificationController::readAll
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/NotificationController.php:11
 * @route '/admin/notifications'
 */
    const readAllForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: readAll.url(options),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\NotificationController::readAll
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/NotificationController.php:11
 * @route '/admin/notifications'
 */
        readAllForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: readAll.url(options),
            method: 'post',
        })
    
    readAll.form = readAllForm
/**
* @see \MoonShine\Laravel\Http\Controllers\NotificationController::read
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/NotificationController.php:18
 * @route '/admin/notifications/{notification}'
 */
export const read = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: read.url(args, options),
    method: 'post',
})

read.definition = {
    methods: ["post"],
    url: '/admin/notifications/{notification}',
} satisfies RouteDefinition<["post"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\NotificationController::read
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/NotificationController.php:18
 * @route '/admin/notifications/{notification}'
 */
read.url = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return read.definition.url
            .replace('{notification}', parsedArgs.notification.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\NotificationController::read
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/NotificationController.php:18
 * @route '/admin/notifications/{notification}'
 */
read.post = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: read.url(args, options),
    method: 'post',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\NotificationController::read
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/NotificationController.php:18
 * @route '/admin/notifications/{notification}'
 */
    const readForm = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: read.url(args, options),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\NotificationController::read
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/NotificationController.php:18
 * @route '/admin/notifications/{notification}'
 */
        readForm.post = (args: { notification: string | number } | [notification: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: read.url(args, options),
            method: 'post',
        })
    
    read.form = readForm
const NotificationController = { readAll, read }

export default NotificationController