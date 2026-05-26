<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as Middleware;

class CustomValidateCsrfToken extends Middleware
{
    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function tokensMatch($request)
    {
        $bearerToken = $request->bearerToken() ?: $request->query('token');
        
        \Illuminate\Support\Facades\Log::info('[CustomCSRF] Path: ' . $request->path() . ' | Method: ' . $request->method() . ' | Has Token: ' . ($bearerToken ? 'Yes' : 'No'));

        if (!empty($bearerToken)) {
            \Illuminate\Support\Facades\Log::info('[CustomCSRF] Token detected. Bypassing CSRF.');
            return true;
        }

        $match = parent::tokensMatch($request);
        \Illuminate\Support\Facades\Log::info('[CustomCSRF] Standard CSRF match result: ' . ($match ? 'Match' : 'Mismatch'));
        return $match;
    }
}
