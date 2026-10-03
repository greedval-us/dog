import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PetThoughtController::store
 * @see app/Http/Controllers/PetThoughtController.php:13
 * @route '/pets/{pet}/thoughts'
 */
export const store = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/pets/{pet}/thoughts',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PetThoughtController::store
 * @see app/Http/Controllers/PetThoughtController.php:13
 * @route '/pets/{pet}/thoughts'
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
* @see \App\Http\Controllers\PetThoughtController::store
 * @see app/Http/Controllers/PetThoughtController.php:13
 * @route '/pets/{pet}/thoughts'
 */
store.post = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PetThoughtController::store
 * @see app/Http/Controllers/PetThoughtController.php:13
 * @route '/pets/{pet}/thoughts'
 */
    const storeForm = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PetThoughtController::store
 * @see app/Http/Controllers/PetThoughtController.php:13
 * @route '/pets/{pet}/thoughts'
 */
        storeForm.post = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
const thoughts = {
    store: Object.assign(store, store),
}

export default thoughts