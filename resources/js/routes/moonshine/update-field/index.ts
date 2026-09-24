import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \MoonShine\Laravel\Http\Controllers\UpdateFieldController::throughColumn
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/UpdateFieldController.php:25
 * @route '/admin/update-field/column/{resourceUri}/{resourceItem}'
 */
export const throughColumn = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: throughColumn.url(args, options),
    method: 'put',
})

throughColumn.definition = {
    methods: ["put"],
    url: '/admin/update-field/column/{resourceUri}/{resourceItem}',
} satisfies RouteDefinition<["put"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\UpdateFieldController::throughColumn
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/UpdateFieldController.php:25
 * @route '/admin/update-field/column/{resourceUri}/{resourceItem}'
 */
throughColumn.url = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                    resourceItem: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                                resourceItem: args.resourceItem,
                }

    return throughColumn.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace('{resourceItem}', parsedArgs.resourceItem.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\UpdateFieldController::throughColumn
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/UpdateFieldController.php:25
 * @route '/admin/update-field/column/{resourceUri}/{resourceItem}'
 */
throughColumn.put = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: throughColumn.url(args, options),
    method: 'put',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\UpdateFieldController::throughColumn
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/UpdateFieldController.php:25
 * @route '/admin/update-field/column/{resourceUri}/{resourceItem}'
 */
    const throughColumnForm = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: throughColumn.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\UpdateFieldController::throughColumn
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/UpdateFieldController.php:25
 * @route '/admin/update-field/column/{resourceUri}/{resourceItem}'
 */
        throughColumnForm.put = (args: { resourceUri: string | number, resourceItem: string | number } | [resourceUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: throughColumn.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    throughColumn.form = throughColumnForm
/**
* @see \MoonShine\Laravel\Http\Controllers\UpdateFieldController::throughRelation
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/UpdateFieldController.php:33
 * @route '/admin/update-field/relation/{resourceUri}/{pageUri}/{resourceItem}'
 */
export const throughRelation = (args: { resourceUri: string | number, pageUri: string | number, resourceItem: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: throughRelation.url(args, options),
    method: 'put',
})

throughRelation.definition = {
    methods: ["put"],
    url: '/admin/update-field/relation/{resourceUri}/{pageUri}/{resourceItem}',
} satisfies RouteDefinition<["put"]>

/**
* @see \MoonShine\Laravel\Http\Controllers\UpdateFieldController::throughRelation
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/UpdateFieldController.php:33
 * @route '/admin/update-field/relation/{resourceUri}/{pageUri}/{resourceItem}'
 */
throughRelation.url = (args: { resourceUri: string | number, pageUri: string | number, resourceItem: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    resourceUri: args[0],
                    pageUri: args[1],
                    resourceItem: args[2],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        resourceUri: args.resourceUri,
                                pageUri: args.pageUri,
                                resourceItem: args.resourceItem,
                }

    return throughRelation.definition.url
            .replace('{resourceUri}', parsedArgs.resourceUri.toString())
            .replace('{pageUri}', parsedArgs.pageUri.toString())
            .replace('{resourceItem}', parsedArgs.resourceItem.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \MoonShine\Laravel\Http\Controllers\UpdateFieldController::throughRelation
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/UpdateFieldController.php:33
 * @route '/admin/update-field/relation/{resourceUri}/{pageUri}/{resourceItem}'
 */
throughRelation.put = (args: { resourceUri: string | number, pageUri: string | number, resourceItem: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: throughRelation.url(args, options),
    method: 'put',
})

    /**
* @see \MoonShine\Laravel\Http\Controllers\UpdateFieldController::throughRelation
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/UpdateFieldController.php:33
 * @route '/admin/update-field/relation/{resourceUri}/{pageUri}/{resourceItem}'
 */
    const throughRelationForm = (args: { resourceUri: string | number, pageUri: string | number, resourceItem: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: throughRelation.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \MoonShine\Laravel\Http\Controllers\UpdateFieldController::throughRelation
 * @see vendor/moonshine/moonshine/src/Laravel/src/Http/Controllers/UpdateFieldController.php:33
 * @route '/admin/update-field/relation/{resourceUri}/{pageUri}/{resourceItem}'
 */
        throughRelationForm.put = (args: { resourceUri: string | number, pageUri: string | number, resourceItem: string | number } | [resourceUri: string | number, pageUri: string | number, resourceItem: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: throughRelation.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    throughRelation.form = throughRelationForm
const updateField = {
    throughColumn: Object.assign(throughColumn, throughColumn),
throughRelation: Object.assign(throughRelation, throughRelation),
}

export default updateField