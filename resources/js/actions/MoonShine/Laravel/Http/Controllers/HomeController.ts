import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
const HomeController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HomeController.url(options),
    method: 'get',
})

HomeController.definition = {
    methods: ["get","head"],
    url: '/admin',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
HomeController.url = (options?: RouteQueryOptions) => {
    return HomeController.definition.url + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
HomeController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: HomeController.url(options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
HomeController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: HomeController.url(options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
    const HomeControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: HomeController.url(options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
        HomeControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: HomeController.url(options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
        HomeControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: HomeController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    HomeController.form = HomeControllerForm
export default HomeController