import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\DogWorkController::index
 * @see app/Http/Controllers/DogWorkController.php:22
 * @route '/dog-work'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/dog-work',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DogWorkController::index
 * @see app/Http/Controllers/DogWorkController.php:22
 * @route '/dog-work'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DogWorkController::index
 * @see app/Http/Controllers/DogWorkController.php:22
 * @route '/dog-work'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\DogWorkController::index
 * @see app/Http/Controllers/DogWorkController.php:22
 * @route '/dog-work'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\DogWorkController::index
 * @see app/Http/Controllers/DogWorkController.php:22
 * @route '/dog-work'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\DogWorkController::index
 * @see app/Http/Controllers/DogWorkController.php:22
 * @route '/dog-work'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\DogWorkController::index
 * @see app/Http/Controllers/DogWorkController.php:22
 * @route '/dog-work'
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
* @see \App\Http\Controllers\DogWorkController::store
 * @see app/Http/Controllers/DogWorkController.php:39
 * @route '/dog-work'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/dog-work',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\DogWorkController::store
 * @see app/Http/Controllers/DogWorkController.php:39
 * @route '/dog-work'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DogWorkController::store
 * @see app/Http/Controllers/DogWorkController.php:39
 * @route '/dog-work'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\DogWorkController::store
 * @see app/Http/Controllers/DogWorkController.php:39
 * @route '/dog-work'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\DogWorkController::store
 * @see app/Http/Controllers/DogWorkController.php:39
 * @route '/dog-work'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\DogWorkController::complete
 * @see app/Http/Controllers/DogWorkController.php:55
 * @route '/dog-work/complete'
 */
export const complete = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/dog-work/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\DogWorkController::complete
 * @see app/Http/Controllers/DogWorkController.php:55
 * @route '/dog-work/complete'
 */
complete.url = (options?: RouteQueryOptions) => {
    return complete.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DogWorkController::complete
 * @see app/Http/Controllers/DogWorkController.php:55
 * @route '/dog-work/complete'
 */
complete.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\DogWorkController::complete
 * @see app/Http/Controllers/DogWorkController.php:55
 * @route '/dog-work/complete'
 */
    const completeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: complete.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\DogWorkController::complete
 * @see app/Http/Controllers/DogWorkController.php:55
 * @route '/dog-work/complete'
 */
        completeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: complete.url(options),
            method: 'post',
        })
    
    complete.form = completeForm
const DogWorkController = { index, store, complete }

export default DogWorkController