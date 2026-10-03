import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
import history from './history'
import thoughts from './thoughts'
import care from './care'
import skills from './skills'
import appearance from './appearance'
/**
* @see \App\Http\Controllers\RetirePetController::__invoke
 * @see app/Http/Controllers/RetirePetController.php:15
 * @route '/pets/{pet}/retire'
 */
export const retire = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retire.url(args, options),
    method: 'post',
})

retire.definition = {
    methods: ["post"],
    url: '/pets/{pet}/retire',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RetirePetController::__invoke
 * @see app/Http/Controllers/RetirePetController.php:15
 * @route '/pets/{pet}/retire'
 */
retire.url = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return retire.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RetirePetController::__invoke
 * @see app/Http/Controllers/RetirePetController.php:15
 * @route '/pets/{pet}/retire'
 */
retire.post = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retire.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RetirePetController::__invoke
 * @see app/Http/Controllers/RetirePetController.php:15
 * @route '/pets/{pet}/retire'
 */
    const retireForm = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: retire.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RetirePetController::__invoke
 * @see app/Http/Controllers/RetirePetController.php:15
 * @route '/pets/{pet}/retire'
 */
        retireForm.post = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: retire.url(args, options),
            method: 'post',
        })

    retire.form = retireForm
const pets = {
    retire: Object.assign(retire, retire),
history: Object.assign(history, history),
thoughts: Object.assign(thoughts, thoughts),
care: Object.assign(care, care),
skills: Object.assign(skills, skills),
appearance: Object.assign(appearance, appearance),
}

export default pets