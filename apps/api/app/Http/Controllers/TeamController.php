<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeamController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account, 'view');

        return response()->json($account->teams->map(fn ($team) => $this->payload($team, $request)));
    }

    public function show(Request $request, Account $account, Team $team): JsonResponse
    {
        $this->auth($request, $account, 'view');
        $this->scoped($account, $team);

        return response()->json($this->payload($team, $request));
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $team = $account->teams()->create($this->validated($request));

        return response()->json($this->payload($team, $request));
    }

    public function update(Request $request, Account $account, Team $team): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $this->scoped($account, $team);
        $team->update($this->validated($request));

        return response()->json($this->payload($team, $request));
    }

    public function destroy(Request $request, Account $account, Team $team): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $this->scoped($account, $team);
        $team->delete();

        return response()->json([]);
    }

    private function auth(Request $request, Account $account, string $ability): void
    {
        Gate::forUser($request->user())->authorize($ability, $account);
    }

    private function scoped(Account $account, Team $team): void
    {
        abort_unless($team->account_id === $account->id, 404);
    }

    private function validated(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string'], 'description' => ['nullable', 'string'], 'allow_auto_assign' => ['nullable', 'boolean'], 'icon' => ['nullable', 'string'], 'icon_color' => ['nullable', 'string']]);
    }

    private function payload(Team $team, Request $request): array
    {
        return ['id' => $team->id, 'name' => $team->name, 'description' => $team->description, 'allow_auto_assign' => $team->allow_auto_assign, 'icon' => $team->icon, 'icon_color' => $team->icon_color, 'account_id' => $team->account_id, 'is_member' => $team->users()->whereKey($request->user()->id)->exists()];
    }
}
