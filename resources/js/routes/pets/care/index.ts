import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PetCareController::store
 * @see app/Http/Controllers/PetCareController.php:19
 * @route '/pets/{pet}/care'
 */
export const store = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/pets/{pet}/care',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PetCareController::store
 * @see app/Http/Controllers/PetCareController.php:19
 * @route '/pets/{pet}/care'
 */
store.url = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { pet: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    pet: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        pet: args.pet,
                }

    return store.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetCareController::store
 * @see app/Http/Controllers/PetCareController.php:19
 * @route '/pets/{pet}/care'
 */
store.post = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PetCareController::store
 * @see app/Http/Controllers/PetCareController.php:19
 * @route '/pets/{pet}/care'
 */
    const storeForm = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PetCareController::store
 * @see app/Http/Controllers/PetCareController.php:19
 * @route '/pets/{pet}/care'
 */
        storeForm.post = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\PetCareController::complete
 * @see app/Http/Controllers/PetCareController.php:37
 * @route '/pets/{pet}/care/complete'
 */
export const complete = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/pets/{pet}/care/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PetCareController::complete
 * @see app/Http/Controllers/PetCareController.php:37
 * @route '/pets/{pet}/care/complete'
 */
complete.url = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { pet: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    pet: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        pet: args.pet,
                }

    return complete.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetCareController::complete
 * @see app/Http/Controllers/PetCareController.php:37
 * @route '/pets/{pet}/care/complete'
 */
complete.post = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PetCareController::complete
 * @see app/Http/Controllers/PetCareController.php:37
 * @route '/pets/{pet}/care/complete'
 */
    const completeForm = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: complete.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PetCareController::complete
 * @see app/Http/Controllers/PetCareController.php:37
 * @route '/pets/{pet}/care/complete'
 */
        completeForm.post = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: complete.url(args, options),
            method: 'post',
        })
    
    complete.form = completeForm
const care = {
    store: Object.assign(store, store),
complete: Object.assign(complete, complete),
}

export default care