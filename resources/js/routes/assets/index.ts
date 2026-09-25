import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:11
 * @route '/media/assets/{asset}/{variant}'
 */
export const image = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: image.url(args, options),
    method: 'get',
})

image.definition = {
    methods: ["get","head"],
    url: '/media/assets/{asset}/{variant}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:11
 * @route '/media/assets/{asset}/{variant}'
 */
image.url = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    asset: args[0],
                    variant: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        asset: typeof args.asset === 'object'
                ? args.asset.id
                : args.asset,
                                variant: args.variant,
                }

    return image.definition.url
            .replace('{asset}', parsedArgs.asset.toString())
            .replace('{variant}', parsedArgs.variant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:11
 * @route '/media/assets/{asset}/{variant}'
 */
image.get = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: image.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:11
 * @route '/media/assets/{asset}/{variant}'
 */
image.head = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: image.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:11
 * @route '/media/assets/{asset}/{variant}'
 */
    const imageForm = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: image.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:11
 * @route '/media/assets/{asset}/{variant}'
 */
        imageForm.get = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: image.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:11
 * @route '/media/assets/{asset}/{variant}'
 */
        imageForm.head = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: image.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    image.form = imageForm
const assets = {
    image: Object.assign(image, image),
}

export default assets