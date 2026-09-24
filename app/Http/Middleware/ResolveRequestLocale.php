<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveRequestLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('localization.supported'));
        $user = $request->user('web');
        $userLocale = $user instanceof User ? $user->locale : null;

        foreach ([$userLocale, $request->cookie('locale'), config('localization.default')] as $locale) {
            if (is_string($locale) && in_array($locale, $supported, true)) {
                app()->setLocale($locale);
                break;
            }
        }

        return $next($request);
    }
}
