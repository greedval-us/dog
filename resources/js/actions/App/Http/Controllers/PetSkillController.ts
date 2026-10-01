import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PetSkillController::store
 * @see app/Http/Controllers/PetSkillController.php:16
 * @route '/pets/{pet}/skills'
 */
export const store = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/pets/{pet}/skills',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PetSkillController::store
 * @see app/Http/Controllers/PetSkillController.php:16
 * @route '/pets/{pet}/skills'
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
* @see \App\Http\Controllers\PetSkillController::store
 * @see app/Http/Controllers/PetSkillController.php:16
 * @route '/pets/{pet}/skills'
 */
store.post = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PetSkillController::store
 * @see app/Http/Controllers/PetSkillController.php:16
 * @route '/pets/{pet}/skills'
 */
    const storeForm = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PetSkillController::store
 * @see app/Http/Controllers/PetSkillController.php:16
 * @route '/pets/{pet}/skills'
 */
        storeForm.post = (args: { pet: string | number } | [pet: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
const PetSkillController = { store }

export default PetSkillController