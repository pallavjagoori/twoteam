<?php

namespace App\Http\Middleware;

use App\Models\AuthToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateChatwootToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->header('access-token');
        $clientId = $request->header('client');
        $uid = $request->header('uid');
        $tokenType = $request->header('token-type');

        if (! is_string($plainToken) || ! is_string($clientId) || ! is_string($uid) || $tokenType !== 'Bearer') {
            return $this->unauthorized();
        }

        $token = AuthToken::query()
            ->with('user')
            ->where('client_id', $clientId)
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('expires_at', '>', now())
            ->first();

        if (! $token || ! hash_equals(strtolower($token->user->email), strtolower($uid))) {
            return $this->unauthorized();
        }

        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('chatwoot_auth_token', $token);

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json([
            'success' => false,
            'errors' => ['Invalid login credentials'],
        ], 401);
    }
}
