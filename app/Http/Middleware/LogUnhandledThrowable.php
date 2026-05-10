<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogUnhandledThrowable
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $throwable) {
            error_log(sprintf(
                '[MiddlewareCatch] %s: %s in %s:%d (path=%s method=%s)',
                $throwable::class,
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
                $request->path(),
                $request->method()
            ));

            return response('Internal Server Error', 500);
        }
    }
}
