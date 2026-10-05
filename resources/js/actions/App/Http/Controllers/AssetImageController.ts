import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:12
 * @route '/media/assets/{asset}/{variant}'
 */
const AssetImageController = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AssetImageController.url(args, options),
    method: 'get',
})

AssetImageController.definition = {
    methods: ["get","head"],
    url: '/media/assets/{asset}/{variant}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:12
 * @route '/media/assets/{asset}/{variant}'
 */
AssetImageController.url = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions) => {
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

    return AssetImageController.definition.url
            .replace('{asset}', parsedArgs.asset.toString())
            .replace('{variant}', parsedArgs.variant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:12
 * @route '/media/assets/{asset}/{variant}'
 */
AssetImageController.get = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AssetImageController.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:12
 * @route '/media/assets/{asset}/{variant}'
 */
AssetImageController.head = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AssetImageController.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:12
 * @route '/media/assets/{asset}/{variant}'
 */
    const AssetImageControllerForm = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: AssetImageController.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:12
 * @route '/media/assets/{asset}/{variant}'
 */
        AssetImageControllerForm.get = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: AssetImageController.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AssetImageController::__invoke
 * @see app/Http/Controllers/AssetImageController.php:12
 * @route '/media/assets/{asset}/{variant}'
 */
        AssetImageControllerForm.head = (args: { asset: number | { id: number }, variant: string | number } | [asset: number | { id: number }, variant: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: AssetImageController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    AssetImageController.form = AssetImageControllerForm
export default AssetImageController