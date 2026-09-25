import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\GameImageController::image
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
export const image = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: image.url(args, options),
    method: 'get',
})

image.definition = {
    methods: ["get","head"],
    url: '/media/breeds/{breed}/{variant}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\GameImageController::image
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
image.url = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions) => {
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

    return image.definition.url
            .replace('{breed}', parsedArgs.breed.toString())
            .replace('{variant}', parsedArgs.variant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameImageController::image
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
image.get = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: image.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\GameImageController::image
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
image.head = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: image.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\GameImageController::image
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
    const imageForm = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: image.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\GameImageController::image
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
        imageForm.get = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: image.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\GameImageController::image
 * @see app/Http/Controllers/GameImageController.php:10
 * @route '/media/breeds/{breed}/{variant}'
 */
        imageForm.head = (args: { breed: string | number, variant: string | number } | [breed: string | number, variant: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: image.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    image.form = imageForm
const breeds = {
    image: Object.assign(image, image),
}

export default breeds