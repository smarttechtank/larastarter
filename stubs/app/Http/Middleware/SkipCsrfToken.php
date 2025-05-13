<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Symfony\Component\HttpFoundation\Response;

class SkipCsrfToken extends VerifyCsrfToken
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // If X-Request-Token header is present, skip CSRF token verification
        if ($request->hasHeader('X-Request-Token')) {
            return $next($request);
        }

        // For regular requests, continue with parent CSRF protection
        return parent::handle($request, $next);
    }
}
