import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\InventoryController::__invoke
 * @see app/Http/Controllers/InventoryController.php:16
 * @route '/inventory'
 */
const InventoryController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: InventoryController.url(options),
    method: 'get',
})

InventoryController.definition = {
    methods: ["get","head"],
    url: '/inventory',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\InventoryController::__invoke
 * @see app/Http/Controllers/InventoryController.php:16
 * @route '/inventory'
 */
InventoryController.url = (options?: RouteQueryOptions) => {
    return InventoryController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\InventoryController::__invoke
 * @see app/Http/Controllers/InventoryController.php:16
 * @route '/inventory'
 */
InventoryController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: InventoryController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\InventoryController::__invoke
 * @see app/Http/Controllers/InventoryController.php:16
 * @route '/inventory'
 */
InventoryController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: InventoryController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\InventoryController::__invoke
 * @see app/Http/Controllers/InventoryController.php:16
 * @route '/inventory'
 */
    const InventoryControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: InventoryController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\InventoryController::__invoke
 * @see app/Http/Controllers/InventoryController.php:16
 * @route '/inventory'
 */
        InventoryControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: InventoryController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\InventoryController::__invoke
 * @see app/Http/Controllers/InventoryController.php:16
 * @route '/inventory'
 */
        InventoryControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: InventoryController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    InventoryController.form = InventoryControllerForm
export default InventoryController