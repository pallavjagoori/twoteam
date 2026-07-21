<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAccount($request, $account, 'view');

        return response()->json($account->users()->orderBy('name')->get()->map(fn ($user) => $this->payload($user, $account)));
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAccount($request, $account, 'update');
        $data = $request->validate(['email' => ['required', 'email'], 'name' => ['required', 'string'], 'role' => ['nullable', 'in:agent,administrator'], 'availability' => ['nullable', 'in:online,busy,offline'], 'auto_offline' => ['nullable', 'boolean']]);
        $user = User::firstOrCreate(['email' => strtolower($data['email'])], ['name' => $data['name'], 'password' => Str::password(), 'email_verified_at' => now()]);
        $account->users()->syncWithoutDetaching([$user->id => ['role' => $data['role'] ?? 'agent', 'availability' => $data['availability'] ?? 'online', 'auto_offline' => $data['auto_offline'] ?? true]]);

        return response()->json($this->payload($user, $account), 200);
    }

    public function update(Request $request, Account $account, User $agent): JsonResponse
    {
        $this->authorizeAccount($request, $account, 'update');
        abort_unless($account->users()->whereKey($agent->id)->exists(), 404);
        $data = $request->validate(['name' => ['sometimes', 'string'], 'role' => ['sometimes', 'in:agent,administrator'], 'availability' => ['sometimes', 'in:online,busy,offline'], 'auto_offline' => ['sometimes', 'boolean']]);
        $agent->update(array_intersect_key($data, ['name' => true]));
        $account->users()->updateExistingPivot($agent->id, array_intersect_key($data, ['role' => true, 'availability' => true, 'auto_offline' => true]));

        return response()->json($this->payload($agent, $account));
    }

    public function destroy(Request $request, Account $account, User $agent): JsonResponse
    {
        $this->authorizeAccount($request, $account, 'update');
        abort_unless($account->users()->whereKey($agent->id)->exists(), 404);
        $account->users()->detach($agent);

        return response()->json([]);
    }

    private function authorizeAccount(Request $request, Account $account, string $ability): void
    {
        Gate::forUser($request->user())->authorize($ability, $account);
    }

    private function payload(User $user, Account $account): array
    {
        $member = $account->users()->whereKey($user->id)->firstOrFail();

        return ['id' => $user->id, 'account_id' => $account->id, 'availability_status' => $member->pivot->availability, 'auto_offline' => (bool) $member->pivot->auto_offline, 'confirmed' => $user->email_verified_at !== null, 'email' => $user->email, 'provider' => $user->provider, 'available_name' => $user->display_name ?: $user->name, 'name' => $user->name, 'role' => $member->pivot->role, 'thumbnail' => ''];
    }
}
