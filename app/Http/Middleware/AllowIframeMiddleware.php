<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowIframeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 1. Remove X-Frame-Options to allow iframing (or set to ALLOW-FROM but it's deprecated)
        $response->headers->remove('X-Frame-Options');

        // 2. Set Content-Security-Policy to allow being embedded by any ancestor
        // For production, you might want to replace '*' with specific ERP domains
        $response->headers->set('Content-Security-Policy', "frame-ancestors *", false);

        // 3. Intercept redirects in iframe mode to propagate token and session flash messages
        if ($response instanceof \Symfony\Component\HttpFoundation\RedirectResponse) {
            $bearerToken = $request->bearerToken() ?: $request->query('token');
            $isIframe = $request->input('is_iframe') === '1' || !!$bearerToken;

            if ($isIframe) {
                $targetUrl = $response->getTargetUrl();

                // Parse URL
                $urlParts = parse_url($targetUrl);
                $queryParams = [];
                if (isset($urlParts['query'])) {
                    parse_str($urlParts['query'], $queryParams);
                }

                // Propagate token if we have it
                if ($bearerToken) {
                    $queryParams['token'] = $bearerToken;
                }

                // Propagate is_iframe flag
                $queryParams['is_iframe'] = '1';

                // Propagate active locale
                $locale = $request->input('locale') ?: app()->getLocale();
                if ($locale && in_array($locale, ['en', 'id'])) {
                    $queryParams['locale'] = $locale;
                }

                // Propagate session flash messages
                if ($request->hasSession()) {
                    $session = $request->session();

                    if ($session->has('success')) {
                        $queryParams['sso_success'] = $session->get('success');
                    }
                    if ($session->has('status')) {
                        // 'status' is commonly used by Laravel auth for success statuses (e.g. OTP sent, reset password success)
                        $queryParams['sso_success'] = $session->get('status');
                    }
                    if ($session->has('error')) {
                        $queryParams['sso_error'] = $session->get('error');
                    }
                    if ($session->has('warning')) {
                        $queryParams['sso_warning'] = $session->get('warning');
                    }
                    if ($session->has('info')) {
                        $queryParams['sso_info'] = $session->get('info');
                    }
                    if ($session->has('hard_block')) {
                        $queryParams['sso_hard_block'] = '1';
                    }
                    if ($session->has('throttle_seconds')) {
                        $queryParams['sso_throttle_seconds'] = $session->get('throttle_seconds');
                    }
                    // Validation errors
                    if ($session->has('errors')) {
                        $errors = $session->get('errors');
                        if ($errors instanceof \Illuminate\Support\ViewErrorBag && $errors->any()) {
                            $queryParams['sso_error'] = $errors->first();
                        } elseif (is_array($errors) && count($errors) > 0) {
                            $queryParams['sso_error'] = reset($errors);
                        }
                    }
                }

                // Rebuild URL
                $newQuery = http_build_query($queryParams);
                $newTargetUrl = '';
                if (isset($urlParts['scheme'])) {
                    $newTargetUrl .= $urlParts['scheme'] . '://';
                }
                if (isset($urlParts['host'])) {
                    $newTargetUrl .= $urlParts['host'];
                    if (isset($urlParts['port'])) {
                        $newTargetUrl .= ':' . $urlParts['port'];
                    }
                }
                if (isset($urlParts['path'])) {
                    $newTargetUrl .= $urlParts['path'];
                }
                if ($newQuery) {
                    $newTargetUrl .= '?' . $newQuery;
                }
                if (isset($urlParts['fragment'])) {
                    $newTargetUrl .= '#' . $urlParts['fragment'];
                }

                $response->setTargetUrl($newTargetUrl);
            }
        }

        return $response;
    }
}
