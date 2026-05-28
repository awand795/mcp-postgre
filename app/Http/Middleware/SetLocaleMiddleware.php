<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale') ?: session('locale');

        if (!$locale) {
            $locale = config('app.locale', 'id');
        }

        if (in_array($locale, ['en', 'id'])) {
            App::setLocale($locale);
        } else {
            App::setLocale('id');
        }

        return $next($request);
    }
}
