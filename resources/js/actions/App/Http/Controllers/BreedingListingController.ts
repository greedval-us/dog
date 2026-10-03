import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\BreedingListingController::store
 * @see app/Http/Controllers/BreedingListingController.php:16
 * @route '/breeding/listings'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/breeding/listings',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\BreedingListingController::store
 * @see app/Http/Controllers/BreedingListingController.php:16
 * @route '/breeding/listings'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\BreedingListingController::store
 * @see app/Http/Controllers/BreedingListingController.php:16
 * @route '/breeding/listings'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\BreedingListingController::store
 * @see app/Http/Controllers/BreedingListingController.php:16
 * @route '/breeding/listings'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\BreedingListingController::store
 * @see app/Http/Controllers/BreedingListingController.php:16
 * @route '/breeding/listings'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\BreedingListingController::destroy
 * @see app/Http/Controllers/BreedingListingController.php:29
 * @route '/breeding/listings/{listing}'
 */
export const destroy = (args: { listing: number | { id: number } } | [listing: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/breeding/listings/{listing}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\BreedingListingController::destroy
 * @see app/Http/Controllers/BreedingListingController.php:29
 * @route '/breeding/listings/{listing}'
 */
destroy.url = (args: { listing: number | { id: number } } | [listing: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { listing: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { listing: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    listing: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        listing: typeof args.listing === 'object'
                ? args.listing.id
                : args.listing,
                }

    return destroy.definition.url
            .replace('{listing}', parsedArgs.listing.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\BreedingListingController::destroy
 * @see app/Http/Controllers/BreedingListingController.php:29
 * @route '/breeding/listings/{listing}'
 */
destroy.delete = (args: { listing: number | { id: number } } | [listing: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\BreedingListingController::destroy
 * @see app/Http/Controllers/BreedingListingController.php:29
 * @route '/breeding/listings/{listing}'
 */
    const destroyForm = (args: { listing: number | { id: number } } | [listing: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\BreedingListingController::destroy
 * @see app/Http/Controllers/BreedingListingController.php:29
 * @route '/breeding/listings/{listing}'
 */
        destroyForm.delete = (args: { listing: number | { id: number } } | [listing: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    destroy.form = destroyForm
const BreedingListingController = { store, destroy }

export default BreedingListingController