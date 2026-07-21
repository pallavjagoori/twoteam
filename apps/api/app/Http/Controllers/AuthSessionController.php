<?php

namespace App\Http\Controllers;

use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthSessionController extends Controller
{
    private const TOKEN_LIFETIME_DAYS = 14;

    public function store(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'errors' => ['Invalid login credentials. Please try again.'],
            ], 401);
        }

        if ($user->email_verified_at === null) {
            return response()->json([
                'success' => false,
                'error_code' => 'user_not_confirmed',
                'errors' => ["A confirmation email was sent to your account at '{$user->email}'."],
            ], 401);
        }

        $plainToken = Str::random(64);
        $clientId = (string) Str::uuid();
        $expiry = now()->addDays(self::TOKEN_LIFETIME_DAYS);
        $user->authTokens()->create([
            'client_id' => $clientId,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiry,
        ]);

        return $this->withTokenHeaders(
            response()->json(['data' => $this->userPayload($user)]),
            $user,
            $clientId,
            $plainToken,
            $expiry->timestamp,
        );
    }

    public function validateToken(Request $request): JsonResponse
    {
        return response()->json([
            'payload' => [
                'success' => true,
                'data' => $this->userPayload($request->user()),
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var AuthToken $token */
        $token = $request->attributes->get('chatwoot_auth_token');
        $token->delete();

        return response()->json(['success' => true]);
    }

    private function userPayload(User $user): array
    {
        return [
            'access_token' => '',
            'account_id' => null,
            'accounts' => [],
            'available_name' => $user->display_name ?: $user->name,
            'avatar_url' => '',
            'confirmed' => $user->email_verified_at !== null,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'id' => $user->id,
            'name' => $user->name,
            'provider' => $user->provider,
            'pubsub_token' => $user->pubsub_token,
            'role' => null,
            'settings' => [],
            'type' => $user->type,
            'uid' => $user->uid ?: $user->email,
            'ui_settings' => $user->ui_settings ?? [],
            'created_at' => $user->created_at,
        ];
    }

    private function withTokenHeaders(
        JsonResponse $response,
        User $user,
        string $clientId,
        string $plainToken,
        int $expiry,
    ): JsonResponse {
        return $response->withHeaders([
            'access-token' => $plainToken,
            'client' => $clientId,
            'expiry' => (string) $expiry,
            'token-type' => 'Bearer',
            'uid' => $user->email,
        ]);
    }
}
