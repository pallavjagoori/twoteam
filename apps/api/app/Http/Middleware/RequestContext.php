<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestedId = $request->header('X-Request-ID');
        $requestId = is_string($requestedId) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $requestedId)
            ? $requestedId
            : (string) Str::uuid();

        Log::withContext(['request_id' => $requestId]);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
