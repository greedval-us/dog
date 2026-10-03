import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
const PetProfileController = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PetProfileController.url(args, options),
    method: 'get',
})

PetProfileController.definition = {
    methods: ["get","head"],
    url: '/pets/{pet}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
PetProfileController.url = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return PetProfileController.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
PetProfileController.get = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PetProfileController.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
PetProfileController.head = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: PetProfileController.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
    const PetProfileControllerForm = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: PetProfileController.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
        PetProfileControllerForm.get = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: PetProfileController.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
        PetProfileControllerForm.head = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: PetProfileController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    PetProfileController.form = PetProfileControllerForm
export default PetProfileController