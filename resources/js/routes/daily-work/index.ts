import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\DailyWorkController::store
 * @see app/Http/Controllers/DailyWorkController.php:15
 * @route '/daily-work'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/daily-work',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\DailyWorkController::store
 * @see app/Http/Controllers/DailyWorkController.php:15
 * @route '/daily-work'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DailyWorkController::store
 * @see app/Http/Controllers/DailyWorkController.php:15
 * @route '/daily-work'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\DailyWorkController::store
 * @see app/Http/Controllers/DailyWorkController.php:15
 * @route '/daily-work'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\DailyWorkController::store
 * @see app/Http/Controllers/DailyWorkController.php:15
 * @route '/daily-work'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const dailyWork = {
    store: Object.assign(store, store),
}

export default dailyWork