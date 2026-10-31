<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('telegraph.webhook.secret');
        abort_unless(is_string($secret) && $secret !== '', 503);
        $received = $request->header('X-Telegram-Bot-Api-Secret-Token');
        abort_unless(is_string($received) && hash_equals($secret, $received), 403);

        return $next($request);
    }
}
