import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \DefStudio\Telegraph\Controllers\WebhookController::webhook
 * @see vendor/defstudio/telegraph/src/Controllers/WebhookController.php:14
 * @route '/telegraph/{token}/webhook'
 */
export const webhook = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: webhook.url(args, options),
    method: 'post',
})

webhook.definition = {
    methods: ["post"],
    url: '/telegraph/{token}/webhook',
} satisfies RouteDefinition<["post"]>

/**
* @see \DefStudio\Telegraph\Controllers\WebhookController::webhook
 * @see vendor/defstudio/telegraph/src/Controllers/WebhookController.php:14
 * @route '/telegraph/{token}/webhook'
 */
webhook.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    token: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        token: args.token,
                }

    return webhook.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \DefStudio\Telegraph\Controllers\WebhookController::webhook
 * @see vendor/defstudio/telegraph/src/Controllers/WebhookController.php:14
 * @route '/telegraph/{token}/webhook'
 */
webhook.post = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: webhook.url(args, options),
    method: 'post',
})

    /**
* @see \DefStudio\Telegraph\Controllers\WebhookController::webhook
 * @see vendor/defstudio/telegraph/src/Controllers/WebhookController.php:14
 * @route '/telegraph/{token}/webhook'
 */
    const webhookForm = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: webhook.url(args, options),
        method: 'post',
    })

            /**
* @see \DefStudio\Telegraph\Controllers\WebhookController::webhook
 * @see vendor/defstudio/telegraph/src/Controllers/WebhookController.php:14
 * @route '/telegraph/{token}/webhook'
 */
        webhookForm.post = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: webhook.url(args, options),
            method: 'post',
        })
    
    webhook.form = webhookForm
const telegraph = {
    webhook: Object.assign(webhook, webhook),
}

export default telegraph