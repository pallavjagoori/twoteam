<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Macro;
use App\Support\MacroExecutor;
use App\Support\MacroPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class MacroController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAccount($request, $account);
        $items = $account->macros()->where(fn ($query) => $query->where('visibility', 'global')->orWhere('created_by_id', $request->user()->id))->with(['createdBy', 'updatedBy'])->orderBy('id')->get();

        return response()->json(['payload' => $items->map(fn ($item) => MacroPayload::make($item))]);
    }

    public function show(Request $request, Account $account, Macro $macro): JsonResponse
    {
        $this->authorizeMacro($request, $account, $macro, false);

        return response()->json(['payload' => MacroPayload::make($macro)]);
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAccount($request, $account);
        $data = $this->validated($request);
        $data['visibility'] = $this->visibility($request, $account, $data['visibility']);
        $macro = $account->macros()->create($data + ['created_by_id' => $request->user()->id, 'updated_by_id' => $request->user()->id]);

        return response()->json(['payload' => MacroPayload::make($macro->load(['createdBy', 'updatedBy']))]);
    }

    public function update(Request $request, Account $account, Macro $macro): JsonResponse
    {
        $this->authorizeMacro($request, $account, $macro, true);
        $data = $this->validated($request);
        $data['visibility'] = $this->visibility($request, $account, $data['visibility']);
        $macro->update($data + ['updated_by_id' => $request->user()->id]);

        return response()->json(['payload' => MacroPayload::make($macro->load(['createdBy', 'updatedBy']))]);
    }

    public function destroy(Request $request, Account $account, Macro $macro): Response
    {
        $this->authorizeMacro($request, $account, $macro, true);
        $macro->delete();

        return response('', 200);
    }

    public function execute(Request $request, Account $account, Macro $macro): Response
    {
        $this->authorizeMacro($request, $account, $macro, false);
        $ids = $request->validate(['conversation_ids' => ['required', 'array'], 'conversation_ids.*' => ['integer']])['conversation_ids'];
        $account->conversations()->whereIn('id', $ids)->get()->each(fn ($conversation) => MacroExecutor::run($macro, $conversation, $request->user()));

        return response('', 200);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string'], 'visibility' => ['required', Rule::in(['personal', 'global'])],
            'actions' => ['required', 'array', 'min:1'], 'actions.*.action_name' => ['required', Rule::in(Macro::ACTIONS)],
            'actions.*.action_params' => ['sometimes', 'array'],
        ]);
    }

    private function authorizeMacro(Request $request, Account $account, Macro $macro, bool $mutating): void
    {
        $this->authorizeAccount($request, $account);
        abort_unless($macro->account_id === $account->id, 404);
        $author = $macro->created_by_id === $request->user()->id;
        $administrator = $this->role($request, $account) === 'administrator';
        abort_unless($macro->visibility === 'global' ? (! $mutating || $administrator) : $author, 403);
        $macro->load(['createdBy', 'updatedBy']);
    }

    private function visibility(Request $request, Account $account, string $visibility): string
    {
        return $this->role($request, $account) === 'administrator' ? $visibility : 'personal';
    }

    private function role(Request $request, Account $account): ?string
    {
        return $request->user()->accounts()->whereKey($account->id)->value('account_users.role');
    }

    private function authorizeAccount(Request $request, Account $account): void
    {
        Gate::forUser($request->user())->authorize('view', $account);
    }
}
