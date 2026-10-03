import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PetHistoryController::index
 * @see app/Http/Controllers/PetHistoryController.php:16
 * @route '/pets/{pet}/history'
 */
export const index = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/pets/{pet}/history',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PetHistoryController::index
 * @see app/Http/Controllers/PetHistoryController.php:16
 * @route '/pets/{pet}/history'
 */
index.url = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return index.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetHistoryController::index
 * @see app/Http/Controllers/PetHistoryController.php:16
 * @route '/pets/{pet}/history'
 */
index.get = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PetHistoryController::index
 * @see app/Http/Controllers/PetHistoryController.php:16
 * @route '/pets/{pet}/history'
 */
index.head = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PetHistoryController::index
 * @see app/Http/Controllers/PetHistoryController.php:16
 * @route '/pets/{pet}/history'
 */
    const indexForm = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PetHistoryController::index
 * @see app/Http/Controllers/PetHistoryController.php:16
 * @route '/pets/{pet}/history'
 */
        indexForm.get = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PetHistoryController::index
 * @see app/Http/Controllers/PetHistoryController.php:16
 * @route '/pets/{pet}/history'
 */
        indexForm.head = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
const history = {
    index: Object.assign(index, index),
}

export default history