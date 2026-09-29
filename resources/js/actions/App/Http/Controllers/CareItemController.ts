import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CareItemController::__invoke
 * @see app/Http/Controllers/CareItemController.php:13
 * @route '/care-items'
 */
const CareItemController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CareItemController.url(options),
    method: 'get',
})

CareItemController.definition = {
    methods: ["get","head"],
    url: '/care-items',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CareItemController::__invoke
 * @see app/Http/Controllers/CareItemController.php:13
 * @route '/care-items'
 */
CareItemController.url = (options?: RouteQueryOptions) => {
    return CareItemController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CareItemController::__invoke
 * @see app/Http/Controllers/CareItemController.php:13
 * @route '/care-items'
 */
CareItemController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CareItemController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CareItemController::__invoke
 * @see app/Http/Controllers/CareItemController.php:13
 * @route '/care-items'
 */
CareItemController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CareItemController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\CareItemController::__invoke
 * @see app/Http/Controllers/CareItemController.php:13
 * @route '/care-items'
 */
    const CareItemControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: CareItemController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\CareItemController::__invoke
 * @see app/Http/Controllers/CareItemController.php:13
 * @route '/care-items'
 */
        CareItemControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: CareItemController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\CareItemController::__invoke
 * @see app/Http/Controllers/CareItemController.php:13
 * @route '/care-items'
 */
        CareItemControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: CareItemController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    CareItemController.form = CareItemControllerForm
export default CareItemController