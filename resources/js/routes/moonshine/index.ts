import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../wayfinder'
import profile from './profile'
import updateField from './update-field'
import notifications from './notifications'
import hasMany from './has-many'
import belongsToManyPivot from './belongs-to-many-pivot'
import crud from './crud'
import resource from './resource'
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
/**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HomeController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HomeController.php:17
 * @route '/admin'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const asyncSearch = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: asyncSearch.url(args, options),
    method: 'get',
})

asyncSearch.definition = {
    methods: ["get","head"],
    url: '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
asyncSearch.url = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    pageUri: args[0],
                    resourceUri: args[1],
                    resourceItem: args[2],
                }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
            "resourceUri",
            "resourceItem",
        ])

    const parsedArgs = {
                        pageUri: args.pageUri,
                                resourceUri: args.resourceUri,
                                resourceItem: args.resourceItem,
                }

    return asyncSearch.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace('{resourceItem?}', parsedArgs.resourceItem?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
asyncSearch.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: asyncSearch.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
asyncSearch.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: asyncSearch.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const asyncSearchForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: asyncSearch.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        asyncSearchForm.get = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: asyncSearch.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\AsyncSearchController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/AsyncSearchController.php:24
 * @route '/admin/async-search/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        asyncSearchForm.head = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: asyncSearch.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    asyncSearch.form = asyncSearchForm
/**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
export const component = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: component.url(args, options),
    method: 'get',
})

component.definition = {
    methods: ["get","head"],
    url: '/admin/component/{pageUri}/{resourceUri?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
component.url = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    pageUri: args[0],
                    resourceUri: args[1],
                }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
            "resourceUri",
        ])

    const parsedArgs = {
                        pageUri: args.pageUri,
                                resourceUri: args.resourceUri,
                }

    return component.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
component.get = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: component.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
component.head = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: component.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
    const componentForm = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: component.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
        componentForm.get = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: component.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\ComponentController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ComponentController.php:17
 * @route '/admin/component/{pageUri}/{resourceUri?}'
 */
        componentForm.head = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: component.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    component.form = componentForm
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
export const method = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: method.url(args, options),
    method: 'get',
})

method.definition = {
    methods: ["get","head","post","put","patch","delete","options"],
    url: '/admin/method/{pageUri}/{resourceUri?}',
} satisfies RouteDefinition<["get","head","post","put","patch","delete","options"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
method.url = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    pageUri: args[0],
                    resourceUri: args[1],
                }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
            "resourceUri",
        ])

    const parsedArgs = {
                        pageUri: args.pageUri,
                                resourceUri: args.resourceUri,
                }

    return method.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
method.get = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: method.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
method.head = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: method.url(args, options),
    method: 'head',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
method.post = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: method.url(args, options),
    method: 'post',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
method.put = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: method.url(args, options),
    method: 'put',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
method.patch = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: method.url(args, options),
    method: 'patch',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
method.delete = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: method.url(args, options),
    method: 'delete',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
method.options = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'options'> => ({
    url: method.url(args, options),
    method: 'options',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
    const methodForm = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: method.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        methodForm.get = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: method.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        methodForm.head = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: method.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        methodForm.post = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: method.url(args, options),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        methodForm.put = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: method.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        methodForm.patch = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: method.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        methodForm.delete = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: method.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\MethodController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/MethodController.php:27
 * @route '/admin/method/{pageUri}/{resourceUri?}'
 */
        methodForm.options = (args: { pageUri: string | number, resourceUri?: string | number } | [pageUri: string | number, resourceUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: method.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'OPTIONS',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    method.form = methodForm
/**
* @see \MoonShine\Laravel\Http\Controllers\ReactiveController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ReactiveController.php:18
 * @route '/admin/reactive/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
export const reactive = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reactive.url(args, options),
    method: 'post',
})

reactive.definition = {
    methods: ["post"],
    url: '/admin/reactive/{pageUri}/{resourceUri?}/{resourceItem?}',
} satisfies RouteDefinition<["post"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\ReactiveController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ReactiveController.php:18
 * @route '/admin/reactive/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
reactive.url = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    pageUri: args[0],
                    resourceUri: args[1],
                    resourceItem: args[2],
                }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
            "resourceUri",
            "resourceItem",
        ])

    const parsedArgs = {
                        pageUri: args.pageUri,
                                resourceUri: args.resourceUri,
                                resourceItem: args.resourceItem,
                }

    return reactive.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceUri?}', parsedArgs.resourceUri?.toString() ?? '')
            .replace('{resourceItem?}', parsedArgs.resourceItem?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\ReactiveController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ReactiveController.php:18
 * @route '/admin/reactive/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
reactive.post = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reactive.url(args, options),
    method: 'post',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\ReactiveController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ReactiveController.php:18
 * @route '/admin/reactive/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
    const reactiveForm = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reactive.url(args, options),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\ReactiveController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/ReactiveController.php:18
 * @route '/admin/reactive/{pageUri}/{resourceUri?}/{resourceItem?}'
 */
        reactiveForm.post = (args: { pageUri: string | number, resourceUri?: string | number, resourceItem?: string | number } | [pageUri: string | number, resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reactive.url(args, options),
            method: 'post',
        })
    
    reactive.form = reactiveForm
/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
export const page = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: page.url(args, options),
    method: 'get',
})

page.definition = {
    methods: ["get","head"],
    url: '/admin/page/{pageUri}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
page.url = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { pageUri: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    pageUri: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        pageUri: args.pageUri,
                }

    return page.definition.url
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
page.get = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: page.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
page.head = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: page.url(args, options),
    method: 'head',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
    const pageForm = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: page.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
        pageForm.get = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: page.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\PageController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/PageController.php:16
 * @route '/admin/page/{pageUri}'
 */
        pageForm.head = (args: { pageUri: string | number } | [pageUri: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: page.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    page.form = pageForm
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
export const handler = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: handler.url(args, options),
    method: 'get',
})

handler.definition = {
    methods: ["get","head","post","put","patch","delete","options"],
    url: '/admin/resource/{resourceUri}/handler/{handlerUri}',
} satisfies RouteDefinition<["get","head","post","put","patch","delete","options"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
handler.url = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                    handlerUri: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                                handlerUri: args.handlerUri,
                }

    return handler.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace('{handlerUri}', parsedArgs.handlerUri.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
handler.get = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: handler.url(args, options),
    method: 'get',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
handler.head = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: handler.url(args, options),
    method: 'head',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
handler.post = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: handler.url(args, options),
    method: 'post',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
handler.put = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: handler.url(args, options),
    method: 'put',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
handler.patch = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: handler.url(args, options),
    method: 'patch',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
handler.delete = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: handler.url(args, options),
    method: 'delete',
})
/**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
handler.options = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteDefinition<'options'> => ({
    url: handler.url(args, options),
    method: 'options',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
    const handlerForm = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: handler.url(args, options),
        method: 'get',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        handlerForm.get = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: handler.url(args, options),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        handlerForm.head = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: handler.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        handlerForm.post = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: handler.url(args, options),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        handlerForm.put = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: handler.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        handlerForm.patch = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: handler.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        handlerForm.delete = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: handler.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
            /**
* @see \MoonShine\Laravel\Http\Controllers\HandlerController::__invoke
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/HandlerController.php:15
 * @route '/admin/resource/{resourceUri}/handler/{handlerUri}'
 */
        handlerForm.options = (args: { resourceUri: string | number, handlerUri: string | number } | [resourceUri: string | number, handlerUri: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: handler.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'OPTIONS',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    handler.form = handlerForm
const moonshine = {
    login: Object.assign(login, login),
authenticate: Object.assign(authenticate, authenticate),
logout: Object.assign(logout, logout),
profile: Object.assign(profile, profile),
index: Object.assign(index, index),
updateField: Object.assign(updateField, updateField),
asyncSearch: Object.assign(asyncSearch, asyncSearch),
notifications: Object.assign(notifications, notifications),
component: Object.assign(component, component),
method: Object.assign(method, method),
reactive: Object.assign(reactive, reactive),
hasMany: Object.assign(hasMany, hasMany),
belongsToManyPivot: Object.assign(belongsToManyPivot, belongsToManyPivot),
page: Object.assign(page, page),
crud: Object.assign(crud, crud),
handler: Object.assign(handler, handler),
resource: Object.assign(resource, resource),
}

export default moonshine