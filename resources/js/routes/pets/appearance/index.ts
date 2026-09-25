import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PetAppearanceController::update
 * @see app/Http/Controllers/PetAppearanceController.php:16
 * @route '/pets/{pet}/appearance'
 */
export const update = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/pets/{pet}/appearance',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\PetAppearanceController::update
 * @see app/Http/Controllers/PetAppearanceController.php:16
 * @route '/pets/{pet}/appearance'
 */
update.url = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetAppearanceController::update
 * @see app/Http/Controllers/PetAppearanceController.php:16
 * @route '/pets/{pet}/appearance'
 */
update.put = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\PetAppearanceController::update
 * @see app/Http/Controllers/PetAppearanceController.php:16
 * @route '/pets/{pet}/appearance'
 */
    const updateForm = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PetAppearanceController::update
 * @see app/Http/Controllers/PetAppearanceController.php:16
 * @route '/pets/{pet}/appearance'
 */
        updateForm.put = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\AssetPurchaseController::purchase
 * @see app/Http/Controllers/AssetPurchaseController.php:16
 * @route '/pets/{pet}/appearance/purchases'
 */
export const purchase = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: purchase.url(args, options),
    method: 'post',
})

purchase.definition = {
    methods: ["post"],
    url: '/pets/{pet}/appearance/purchases',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AssetPurchaseController::purchase
 * @see app/Http/Controllers/AssetPurchaseController.php:16
 * @route '/pets/{pet}/appearance/purchases'
 */
purchase.url = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return purchase.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AssetPurchaseController::purchase
 * @see app/Http/Controllers/AssetPurchaseController.php:16
 * @route '/pets/{pet}/appearance/purchases'
 */
purchase.post = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: purchase.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AssetPurchaseController::purchase
 * @see app/Http/Controllers/AssetPurchaseController.php:16
 * @route '/pets/{pet}/appearance/purchases'
 */
    const purchaseForm = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: purchase.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AssetPurchaseController::purchase
 * @see app/Http/Controllers/AssetPurchaseController.php:16
 * @route '/pets/{pet}/appearance/purchases'
 */
        purchaseForm.post = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: purchase.url(args, options),
            method: 'post',
        })
    
    purchase.form = purchaseForm
const appearance = {
    update: Object.assign(update, update),
purchase: Object.assign(purchase, purchase),
}

export default appearance