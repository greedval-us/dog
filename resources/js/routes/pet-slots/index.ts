import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\PetSlotController::store
 * @see app/Http/Controllers/PetSlotController.php:15
 * @route '/pet-slots'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/pet-slots',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PetSlotController::store
 * @see app/Http/Controllers/PetSlotController.php:15
 * @route '/pet-slots'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetSlotController::store
 * @see app/Http/Controllers/PetSlotController.php:15
 * @route '/pet-slots'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PetSlotController::store
 * @see app/Http/Controllers/PetSlotController.php:15
 * @route '/pet-slots'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PetSlotController::store
 * @see app/Http/Controllers/PetSlotController.php:15
 * @route '/pet-slots'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const petSlots = {
    store: Object.assign(store, store),
}

export default petSlots