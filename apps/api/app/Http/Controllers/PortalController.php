<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Portal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PortalController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account, 'view');

        return response()->json(['payload' => $account->portals()->orderBy('name')->get()->map(fn ($portal) => $this->payload($portal))]);
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $portal = $account->portals()->create($this->validated($request));

        return response()->json($this->payload($portal));
    }

    public function update(Request $request, Account $account, Portal $portal): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $this->scoped($account, $portal);
        $portal->update($this->validated($request, true));

        return response()->json($this->payload($portal));
    }

    public function destroy(Request $request, Account $account, Portal $portal): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $this->scoped($account, $portal);
        $portal->delete();

        return response()->json(['message' => 'Portal deleted']);
    }

    private function validated(Request $request, bool $sometimes = false): array
    {
        $presence = $sometimes ? 'sometimes' : 'required';

        return $request->validate(['name' => [$presence, 'string'], 'slug' => [$presence, 'alpha_dash', 'unique:portals,slug'.($sometimes ? ','.$request->route('portal')->id : '')], 'custom_domain' => ['sometimes', 'nullable', 'string'], 'default_locale' => ['sometimes', 'string'], 'archived' => ['sometimes', 'boolean']]);
    }

    private function payload(Portal $portal): array
    {
        return ['id' => $portal->id, 'name' => $portal->name, 'slug' => $portal->slug, 'custom_domain' => $portal->custom_domain, 'default_locale' => $portal->default_locale, 'archived' => $portal->archived];
    }

    private function auth(Request $request, Account $account, string $ability): void
    {
        Gate::forUser($request->user())->authorize($ability, $account);
    }

    private function scoped(Account $account, Portal $portal): void
    {
        abort_unless($portal->account_id === $account->id, 404);
    }
}
