import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
const PetPedigreeController = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PetPedigreeController.url(args, options),
    method: 'get',
})

PetPedigreeController.definition = {
    methods: ["get","head"],
    url: '/pets/{pet}/pedigree',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
PetPedigreeController.url = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return PetPedigreeController.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
PetPedigreeController.get = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PetPedigreeController.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
PetPedigreeController.head = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: PetPedigreeController.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
    const PetPedigreeControllerForm = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: PetPedigreeController.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
        PetPedigreeControllerForm.get = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: PetPedigreeController.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
        PetPedigreeControllerForm.head = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: PetPedigreeController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    PetPedigreeController.form = PetPedigreeControllerForm
export default PetPedigreeController