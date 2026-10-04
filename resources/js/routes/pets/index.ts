import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
import history from './history'
import thoughts from './thoughts'
import care from './care'
import skills from './skills'
import appearance from './appearance'
/**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
export const show = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/pets/{pet}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
show.url = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
show.get = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
show.head = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
    const showForm = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
        showForm.get = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PetProfileController::__invoke
 * @see app/Http/Controllers/PetProfileController.php:12
 * @route '/pets/{pet}'
 */
        showForm.head = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
export const pedigree = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pedigree.url(args, options),
    method: 'get',
})

pedigree.definition = {
    methods: ["get","head"],
    url: '/pets/{pet}/pedigree',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
pedigree.url = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return pedigree.definition.url
            .replace('{pet}', parsedArgs.pet.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
pedigree.get = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pedigree.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
pedigree.head = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pedigree.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
    const pedigreeForm = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: pedigree.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
        pedigreeForm.get = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pedigree.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PetPedigreeController::__invoke
 * @see app/Http/Controllers/PetPedigreeController.php:12
 * @route '/pets/{pet}/pedigree'
 */
        pedigreeForm.head = (args: { pet: number | { id: number } } | [pet: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pedigree.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    pedigree.form = pedigreeForm
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
    show: Object.assign(show, show),
pedigree: Object.assign(pedigree, pedigree),
retire: Object.assign(retire, retire),
history: Object.assign(history, history),
thoughts: Object.assign(thoughts, thoughts),
care: Object.assign(care, care),
skills: Object.assign(skills, skills),
appearance: Object.assign(appearance, appearance),
}

export default pets