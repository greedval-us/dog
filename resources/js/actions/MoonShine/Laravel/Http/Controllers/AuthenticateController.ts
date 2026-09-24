import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::login
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:25
 * @route '/admin/login'
 */
export const login = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

login.definition = {
    methods: ["get","head"],
    url: '/admin/login',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::login
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:25
 * @route '/admin/login'
 */
login.url = (options?: RouteQueryOptions) => {
    return login.definition.url + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::login
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:25
 * @route '/admin/login'
 */
login.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::login
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:25
 * @route '/admin/login'
 */
login.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: login.url(options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::login
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:25
 * @route '/admin/login'
 */
    const loginForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: login.url(options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::login
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:25
 * @route '/admin/login'
 */
        loginForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: login.url(options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::login
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:25
 * @route '/admin/login'
 */
        loginForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: login.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    login.form = loginForm
/**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::authenticate
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:47
 * @route '/admin/authenticate'
 */
export const authenticate = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: authenticate.url(options),
    method: 'post',
})

authenticate.definition = {
    methods: ["post"],
    url: '/admin/authenticate',
} satisfies RouteDefinition<["post"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::authenticate
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:47
 * @route '/admin/authenticate'
 */
authenticate.url = (options?: RouteQueryOptions) => {
    return authenticate.definition.url + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::authenticate
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:47
 * @route '/admin/authenticate'
 */
authenticate.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: authenticate.url(options),
    method: 'post',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::authenticate
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:47
 * @route '/admin/authenticate'
 */
    const authenticateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: authenticate.url(options),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::authenticate
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:47
 * @route '/admin/authenticate'
 */
        authenticateForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: authenticate.url(options),
            method: 'post',
        })
    
    authenticate.form = authenticateForm
/**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::logout
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:75
 * @route '/admin/logout'
 */
export const logout = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: logout.url(options),
    method: 'delete',
})

logout.definition = {
    methods: ["delete"],
    url: '/admin/logout',
} satisfies RouteDefinition<["delete"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::logout
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:75
 * @route '/admin/logout'
 */
logout.url = (options?: RouteQueryOptions) => {
    return logout.definition.url + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::logout
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:75
 * @route '/admin/logout'
 */
logout.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: logout.url(options),
    method: 'delete',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::logout
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:75
 * @route '/admin/logout'
 */
    const logoutForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: logout.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\AuthenticateController::logout
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AuthenticateController.php:75
 * @route '/admin/logout'
 */
        logoutForm.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: logout.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    logout.form = logoutForm
const AuthenticateController = { login, authenticate, logout }

export default AuthenticateController