<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Label;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LabelController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account, 'view');

        return response()->json(['payload' => $account->labels()->orderBy('title')->get()->map(fn ($label) => $this->payload($label))]);
    }

    public function show(Request $request, Account $account, Label $label): JsonResponse
    {
        $this->auth($request, $account, 'view');
        $this->scoped($account, $label);

        return response()->json($this->payload($label));
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $label = $account->labels()->create($this->validated($request));

        return response()->json($this->payload($label));
    }

    public function update(Request $request, Account $account, Label $label): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $this->scoped($account, $label);
        $label->update($this->validated($request));

        return response()->json($this->payload($label));
    }

    public function destroy(Request $request, Account $account, Label $label): JsonResponse
    {
        $this->auth($request, $account, 'update');
        $this->scoped($account, $label);
        $label->delete();

        return response()->json([]);
    }

    private function validated(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string'], 'description' => ['nullable', 'string'], 'color' => ['nullable', 'string'], 'show_on_sidebar' => ['nullable', 'boolean']]);
    }

    private function payload(Label $label): array
    {
        return ['id' => $label->id, 'title' => $label->title, 'description' => $label->description, 'color' => $label->color, 'show_on_sidebar' => $label->show_on_sidebar];
    }

    private function auth(Request $request, Account $account, string $ability): void
    {
        Gate::forUser($request->user())->authorize($ability, $account);
    }

    private function scoped(Account $account, Label $label): void
    {
        abort_unless($label->account_id === $account->id, 404);
    }
}
