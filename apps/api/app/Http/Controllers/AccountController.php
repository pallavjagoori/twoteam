<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Support\AccountPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function show(Request $request, Account $account): JsonResponse
    {
        $this->membership($request, $account);
        Gate::forUser($request->user())->authorize('view', $account);

        return response()->json(AccountPayload::make($account));
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        $this->membership($request, $account);
        Gate::forUser($request->user())->authorize('update', $account);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'support_email' => ['sometimes', 'nullable', 'email'],
            'settings' => ['sometimes', 'array'],
        ]);
        $account->update($data);

        return response()->json(AccountPayload::make($account->fresh()));
    }

    public function active(Request $request, Account $account): JsonResponse
    {
        $membership = $this->membership($request, $account);
        Gate::forUser($request->user())->authorize('updateActiveAt', $account);
        $membership->pivot->update(['active_at' => now()]);

        return response()->json([], 200);
    }

    private function membership(Request $request, Account $account): object
    {
        $membership = $request->user()->accounts()->whereKey($account->id)->first();
        abort_unless($membership, 404);

        return $membership;
    }
}
