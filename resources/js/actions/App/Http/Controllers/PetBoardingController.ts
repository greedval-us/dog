import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PetBoardingController::update
 * @see app/Http/Controllers/PetBoardingController.php:16
 * @route '/settings/boarding'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/settings/boarding',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\PetBoardingController::update
 * @see app/Http/Controllers/PetBoardingController.php:16
 * @route '/settings/boarding'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PetBoardingController::update
 * @see app/Http/Controllers/PetBoardingController.php:16
 * @route '/settings/boarding'
 */
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\PetBoardingController::update
 * @see app/Http/Controllers/PetBoardingController.php:16
 * @route '/settings/boarding'
 */
    const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PetBoardingController::update
 * @see app/Http/Controllers/PetBoardingController.php:16
 * @route '/settings/boarding'
 */
        updateForm.put = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const PetBoardingController = { update }

export default PetBoardingController