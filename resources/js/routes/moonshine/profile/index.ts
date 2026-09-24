import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\ProfileController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ProfileController.php:24
 * @route '/admin/profile'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/profile',
} satisfies RouteDefinition<["post"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\ProfileController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ProfileController.php:24
 * @route '/admin/profile'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\ProfileController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ProfileController.php:24
 * @route '/admin/profile'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\ProfileController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ProfileController.php:24
 * @route '/admin/profile'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\ProfileController::store
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ProfileController.php:24
 * @route '/admin/profile'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const profile = {
    store: Object.assign(store, store),
}

export default profile