import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PetSurrenderController::__invoke
 * @see app/Http/Controllers/PetSurrenderController.php:16
 * @route '/pets/{pet}/surrender'
 */
const PetSurrenderController = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: PetSurrenderController.url(args, options),
    method: 'post',
})

PetSurrenderController.definition = {
    methods: ["post"],
    url: '/pets/{pet}/surrender',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PetSurrenderController::__invoke
 * @see app/Http/Controllers/PetSurrenderController.php:16
 * @route '/pets/{pet}/surrender'
 */
PetSurrenderController.url = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return PetSurrenderController.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetSurrenderController::__invoke
 * @see app/Http/Controllers/PetSurrenderController.php:16
 * @route '/pets/{pet}/surrender'
 */
PetSurrenderController.post = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: PetSurrenderController.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PetSurrenderController::__invoke
 * @see app/Http/Controllers/PetSurrenderController.php:16
 * @route '/pets/{pet}/surrender'
 */
    const PetSurrenderControllerForm = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: PetSurrenderController.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PetSurrenderController::__invoke
 * @see app/Http/Controllers/PetSurrenderController.php:16
 * @route '/pets/{pet}/surrender'
 */
        PetSurrenderControllerForm.post = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: PetSurrenderController.url(args, options),
            method: 'post',
        })
    
    PetSurrenderController.form = PetSurrenderControllerForm
export default PetSurrenderController