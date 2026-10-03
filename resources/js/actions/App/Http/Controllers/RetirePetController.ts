import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RetirePetController::__invoke
 * @see app/Http/Controllers/RetirePetController.php:15
 * @route '/pets/{pet}/retire'
 */
const RetirePetController = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: RetirePetController.url(args, options),
    method: 'post',
})

RetirePetController.definition = {
    methods: ["post"],
    url: '/pets/{pet}/retire',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RetirePetController::__invoke
 * @see app/Http/Controllers/RetirePetController.php:15
 * @route '/pets/{pet}/retire'
 */
RetirePetController.url = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return RetirePetController.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RetirePetController::__invoke
 * @see app/Http/Controllers/RetirePetController.php:15
 * @route '/pets/{pet}/retire'
 */
RetirePetController.post = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: RetirePetController.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RetirePetController::__invoke
 * @see app/Http/Controllers/RetirePetController.php:15
 * @route '/pets/{pet}/retire'
 */
    const RetirePetControllerForm = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: RetirePetController.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RetirePetController::__invoke
 * @see app/Http/Controllers/RetirePetController.php:15
 * @route '/pets/{pet}/retire'
 */
        RetirePetControllerForm.post = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: RetirePetController.url(args, options),
            method: 'post',
        })
    
    RetirePetController.form = RetirePetControllerForm
export default RetirePetController