import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AssetPurchaseController::store
 * @see app/Http/Controllers/AssetPurchaseController.php:16
 * @route '/pets/{pet}/appearance/purchases'
 */
export const store = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/pets/{pet}/appearance/purchases',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AssetPurchaseController::store
 * @see app/Http/Controllers/AssetPurchaseController.php:16
 * @route '/pets/{pet}/appearance/purchases'
 */
store.url = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { pet: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { pet: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    pet: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        pet: typeof args.pet === 'object'
                ? args.pet.id
                : args.pet,
                }

    return store.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AssetPurchaseController::store
 * @see app/Http/Controllers/AssetPurchaseController.php:16
 * @route '/pets/{pet}/appearance/purchases'
 */
store.post = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AssetPurchaseController::store
 * @see app/Http/Controllers/AssetPurchaseController.php:16
 * @route '/pets/{pet}/appearance/purchases'
 */
    const storeForm = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AssetPurchaseController::store
 * @see app/Http/Controllers/AssetPurchaseController.php:16
 * @route '/pets/{pet}/appearance/purchases'
 */
        storeForm.post = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
const AssetPurchaseController = { store }

export default AssetPurchaseController