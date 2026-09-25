import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:20
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
 * @see app/Http/Controllers/KennelController.php:20
 * @route '/kennel'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:20
 * @route '/kennel'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:20
 * @route '/kennel'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:20
 * @route '/kennel'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:20
 * @route '/kennel'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\KennelController::index
 * @see app/Http/Controllers/KennelController.php:20
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
 * @see app/Http/Controllers/KennelController.php:32
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
 * @see app/Http/Controllers/KennelController.php:32
 * @route '/kennel'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\KennelController::store
 * @see app/Http/Controllers/KennelController.php:32
 * @route '/kennel'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\KennelController::store
 * @see app/Http/Controllers/KennelController.php:32
 * @route '/kennel'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\KennelController::store
 * @see app/Http/Controllers/KennelController.php:32
 * @route '/kennel'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const KennelController = { index, store }

export default KennelController