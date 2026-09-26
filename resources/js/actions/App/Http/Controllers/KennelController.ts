import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:24
 * @route '/kennel'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/kennel',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:24
 * @route '/kennel'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:24
 * @route '/kennel'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:24
 * @route '/kennel'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:24
 * @route '/kennel'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:24
 * @route '/kennel'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:24
 * @route '/kennel'
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
* @see \App\Http\Controllers\KennelController::store
 * @see app/Http/Controllers/KennelController.php:40
 * @route '/kennel'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/kennel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\KennelController::store
 * @see app/Http/Controllers/KennelController.php:40
 * @route '/kennel'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\KennelController::store
 * @see app/Http/Controllers/KennelController.php:40
 * @route '/kennel'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\KennelController::store
 * @see app/Http/Controllers/KennelController.php:40
 * @route '/kennel'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\KennelController::store
 * @see app/Http/Controllers/KennelController.php:40
 * @route '/kennel'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\KennelController::purchase
 * @see app/Http/Controllers/KennelController.php:64
 * @route '/kennel/purchases'
 */
export const purchase = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: purchase.url(options),
    method: 'post',
})

purchase.definition = {
    methods: ["post"],
    url: '/kennel/purchases',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\KennelController::purchase
 * @see app/Http/Controllers/KennelController.php:64
 * @route '/kennel/purchases'
 */
purchase.url = (options?: RouteQueryOptions) => {
    return purchase.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\KennelController::purchase
 * @see app/Http/Controllers/KennelController.php:64
 * @route '/kennel/purchases'
 */
purchase.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: purchase.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\KennelController::purchase
 * @see app/Http/Controllers/KennelController.php:64
 * @route '/kennel/purchases'
 */
    const purchaseForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: purchase.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\KennelController::purchase
 * @see app/Http/Controllers/KennelController.php:64
 * @route '/kennel/purchases'
 */
        purchaseForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: purchase.url(options),
            method: 'post',
        })

    purchase.form = purchaseForm
const KennelController = { index, store, purchase }

export default KennelController