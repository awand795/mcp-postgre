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
        
        \Illuminate\Support\Facades\Log::info('[CustomCSRF] Path: ' . $request->path() . ' | Method: ' . $request->method() . ' | Has Token: ' . ($bearerToken ? 'Yes (' . substr($bearerToken, 0, 10) . '...)' : 'No'));

        if ($bearerToken) {
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);
            if ($accessToken && $accessToken->tokenable) {
                \Illuminate\Support\Facades\Log::info('[CustomCSRF] Token validated successfully. Bypassing CSRF.');
                return true;
            }
            \Illuminate\Support\Facades\Log::warning('[CustomCSRF] Token found but failed validation in database.');
        }

        $match = parent::tokensMatch($request);
        \Illuminate\Support\Facades\Log::info('[CustomCSRF] Standard CSRF match result: ' . ($match ? 'Match' : 'Mismatch'));
        return $match;
    }
}
