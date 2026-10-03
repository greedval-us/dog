import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\VeterinarianController::index
 * @see app/Http/Controllers/VeterinarianController.php:20
 * @route '/veterinarian'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/veterinarian',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\VeterinarianController::index
 * @see app/Http/Controllers/VeterinarianController.php:20
 * @route '/veterinarian'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\VeterinarianController::index
 * @see app/Http/Controllers/VeterinarianController.php:20
 * @route '/veterinarian'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\VeterinarianController::index
 * @see app/Http/Controllers/VeterinarianController.php:20
 * @route '/veterinarian'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\VeterinarianController::index
 * @see app/Http/Controllers/VeterinarianController.php:20
 * @route '/veterinarian'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\VeterinarianController::index
 * @see app/Http/Controllers/VeterinarianController.php:20
 * @route '/veterinarian'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\VeterinarianController::index
 * @see app/Http/Controllers/VeterinarianController.php:20
 * @route '/veterinarian'
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
* @see \App\Http\Controllers\VeterinarianController::store
 * @see app/Http/Controllers/VeterinarianController.php:37
 * @route '/veterinarian'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/veterinarian',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\VeterinarianController::store
 * @see app/Http/Controllers/VeterinarianController.php:37
 * @route '/veterinarian'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\VeterinarianController::store
 * @see app/Http/Controllers/VeterinarianController.php:37
 * @route '/veterinarian'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\VeterinarianController::store
 * @see app/Http/Controllers/VeterinarianController.php:37
 * @route '/veterinarian'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\VeterinarianController::store
 * @see app/Http/Controllers/VeterinarianController.php:37
 * @route '/veterinarian'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const VeterinarianController = { index, store }

export default VeterinarianController