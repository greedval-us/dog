import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\GameImageController::breed
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
export const breed = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: breed.url(args, options),
    method: 'get',
})

breed.definition = {
    methods: ["get","head"],
    url: '/media/breeds/{breed}/{variant}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\GameImageController::breed
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
breed.url = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    breed: args[0],
                    variant: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        breed: args.breed,
                                variant: args.variant,
                }

    return breed.definition.url
            .replace('{breed}', parsedArgs.breed.toString())
            .replace('{variant}', parsedArgs.variant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameImageController::breed
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
breed.get = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: breed.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\GameImageController::breed
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
breed.head = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: breed.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\GameImageController::breed
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
    const breedForm = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: breed.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\GameImageController::breed
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
        breedForm.get = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: breed.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\GameImageController::breed
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
        breedForm.head = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: breed.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    breed.form = breedForm
/**
* @see \App\Http\Controllers\GameImageController::scene
 * @see app/Http/Controllers/GameImageController.php:18
 * @route '/media/pet-scene'
 */
export const scene = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: scene.url(options),
    method: 'get',
})

scene.definition = {
    methods: ["get","head"],
    url: '/media/pet-scene',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\GameImageController::scene
 * @see app/Http/Controllers/GameImageController.php:18
 * @route '/media/pet-scene'
 */
scene.url = (options?: RouteQueryOptions) => {
    return scene.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameImageController::scene
 * @see app/Http/Controllers/GameImageController.php:18
 * @route '/media/pet-scene'
 */
scene.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: scene.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\GameImageController::scene
 * @see app/Http/Controllers/GameImageController.php:18
 * @route '/media/pet-scene'
 */
scene.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: scene.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\GameImageController::scene
 * @see app/Http/Controllers/GameImageController.php:18
 * @route '/media/pet-scene'
 */
    const sceneForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: scene.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\GameImageController::scene
 * @see app/Http/Controllers/GameImageController.php:18
 * @route '/media/pet-scene'
 */
        sceneForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: scene.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\GameImageController::scene
 * @see app/Http/Controllers/GameImageController.php:18
 * @route '/media/pet-scene'
 */
        sceneForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: scene.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    scene.form = sceneForm
const GameImageController = { breed, scene }

export default GameImageController