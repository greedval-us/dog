import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\BreedingController::index
 * @see app/Http/Controllers/BreedingController.php:19
 * @route '/breeding'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/breeding',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\BreedingController::index
 * @see app/Http/Controllers/BreedingController.php:19
 * @route '/breeding'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\BreedingController::index
 * @see app/Http/Controllers/BreedingController.php:19
 * @route '/breeding'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\BreedingController::index
 * @see app/Http/Controllers/BreedingController.php:19
 * @route '/breeding'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\BreedingController::index
 * @see app/Http/Controllers/BreedingController.php:19
 * @route '/breeding'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\BreedingController::index
 * @see app/Http/Controllers/BreedingController.php:19
 * @route '/breeding'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\BreedingController::index
 * @see app/Http/Controllers/BreedingController.php:19
 * @route '/breeding'
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
* @see \App\Http\Controllers\BreedingController::store
 * @see app/Http/Controllers/BreedingController.php:30
 * @route '/breeding'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/breeding',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\BreedingController::store
 * @see app/Http/Controllers/BreedingController.php:30
 * @route '/breeding'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\BreedingController::store
 * @see app/Http/Controllers/BreedingController.php:30
 * @route '/breeding'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\BreedingController::store
 * @see app/Http/Controllers/BreedingController.php:30
 * @route '/breeding'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\BreedingController::store
 * @see app/Http/Controllers/BreedingController.php:30
 * @route '/breeding'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const BreedingController = { index, store }

export default BreedingController