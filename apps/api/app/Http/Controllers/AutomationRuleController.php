<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AutomationRule;
use App\Models\Macro;
use App\Support\AutomationPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AutomationRuleController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAdmin($request, $account);

        return response()->json(['payload' => $account->automationRules()->orderBy('id')->get()->map(fn ($rule) => AutomationPayload::make($rule))]);
    }

    public function show(Request $request, Account $account, AutomationRule $automationRule): JsonResponse
    {
        $this->authorizeAdmin($request, $account);
        $this->scoped($account, $automationRule);

        return response()->json(['payload' => AutomationPayload::make($automationRule)]);
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAdmin($request, $account);
        $rule = $account->automationRules()->create($this->validated($request));

        return response()->json(AutomationPayload::make($rule));
    }

    public function update(Request $request, Account $account, AutomationRule $automationRule): JsonResponse
    {
        $this->authorizeAdmin($request, $account);
        $this->scoped($account, $automationRule);
        $automationRule->update($this->validated($request));

        return response()->json(['payload' => AutomationPayload::make($automationRule)]);
    }

    public function destroy(Request $request, Account $account, AutomationRule $automationRule): Response
    {
        $this->authorizeAdmin($request, $account);
        $this->scoped($account, $automationRule);
        $automationRule->delete();

        return response('', 200);
    }

    public function clone(Request $request, Account $account, AutomationRule $automationRule): JsonResponse
    {
        $this->authorizeAdmin($request, $account);
        $this->scoped($account, $automationRule);
        $copy = $automationRule->replicate();
        $copy->save();

        return response()->json(['payload' => AutomationPayload::make($copy)]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string'], 'description' => ['nullable', 'string'], 'event_name' => ['required', Rule::in(AutomationRule::EVENTS)], 'active' => ['sometimes', 'boolean'],
            'conditions' => ['required', 'array'], 'conditions.*.attribute_key' => ['required', Rule::in(['content', 'message_type', 'status', 'priority', 'inbox_id', 'labels'])],
            'conditions.*.filter_operator' => ['required', Rule::in(['equal_to', 'not_equal_to', 'contains', 'does_not_contain'])], 'conditions.*.query_operator' => ['nullable', Rule::in(['AND', 'OR', 'and', 'or'])], 'conditions.*.values' => ['required', 'array'],
            'actions' => ['required', 'array', 'min:1'], 'actions.*.action_name' => ['required', Rule::in(Macro::ACTIONS)], 'actions.*.action_params' => ['sometimes', 'array'],
        ]);
    }

    private function authorizeAdmin(Request $request, Account $account): void
    {
        Gate::forUser($request->user())->authorize('update', $account);
    }

    private function scoped(Account $account, AutomationRule $rule): void
    {
        abort_unless($rule->account_id === $account->id, 404);
    }
}
