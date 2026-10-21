import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\PetSaleController::index
 * @see app/Http/Controllers/PetSaleController.php:24
 * @route '/pet-market'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/pet-market',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PetSaleController::index
 * @see app/Http/Controllers/PetSaleController.php:24
 * @route '/pet-market'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetSaleController::index
 * @see app/Http/Controllers/PetSaleController.php:24
 * @route '/pet-market'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PetSaleController::index
 * @see app/Http/Controllers/PetSaleController.php:24
 * @route '/pet-market'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PetSaleController::index
 * @see app/Http/Controllers/PetSaleController.php:24
 * @route '/pet-market'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PetSaleController::index
 * @see app/Http/Controllers/PetSaleController.php:24
 * @route '/pet-market'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PetSaleController::index
 * @see app/Http/Controllers/PetSaleController.php:24
 * @route '/pet-market'
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
* @see \App\Http\Controllers\PetSaleController::purchase
 * @see app/Http/Controllers/PetSaleController.php:59
 * @route '/pet-market/{listing}/purchase'
 */
export const purchase = (args: { listing: number | { id: number } } | [listing: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: purchase.url(args, options),
    method: 'post',
})

purchase.definition = {
    methods: ["post"],
    url: '/pet-market/{listing}/purchase',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PetSaleController::purchase
 * @see app/Http/Controllers/PetSaleController.php:59
 * @route '/pet-market/{listing}/purchase'
 */
purchase.url = (args: { listing: number | { id: number } } | [listing: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { listing: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { listing: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    listing: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        listing: typeof args.listing === 'object'
                ? args.listing.id
                : args.listing,
                }

    return purchase.definition.url
            .replace('{listing}', parsedArgs.listing.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetSaleController::purchase
 * @see app/Http/Controllers/PetSaleController.php:59
 * @route '/pet-market/{listing}/purchase'
 */
purchase.post = (args: { listing: number | { id: number } } | [listing: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: purchase.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PetSaleController::purchase
 * @see app/Http/Controllers/PetSaleController.php:59
 * @route '/pet-market/{listing}/purchase'
 */
    const purchaseForm = (args: { listing: number | { id: number } } | [listing: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: purchase.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PetSaleController::purchase
 * @see app/Http/Controllers/PetSaleController.php:59
 * @route '/pet-market/{listing}/purchase'
 */
        purchaseForm.post = (args: { listing: number | { id: number } } | [listing: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: purchase.url(args, options),
            method: 'post',
        })
    
    purchase.form = purchaseForm
const petMarket = {
    index: Object.assign(index, index),
purchase: Object.assign(purchase, purchase),
}

export default petMarket