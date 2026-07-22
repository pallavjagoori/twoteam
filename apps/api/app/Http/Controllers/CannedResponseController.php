<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CannedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CannedResponseController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAccount($request, $account);
        $query = $account->cannedResponses();
        if ($request->filled('search')) {
            $search = mb_strtolower($request->string('search')->toString());
            $query->where(fn ($item) => $item->whereRaw('LOWER(short_code) LIKE ?', ["%{$search}%"])->orWhereRaw('LOWER(content) LIKE ?', ["%{$search}%"]));
        }

        return response()->json($query->orderBy('short_code')->get());
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAccount($request, $account);
        $item = $account->cannedResponses()->create($this->validated($request, $account));

        return response()->json($item);
    }

    public function update(Request $request, Account $account, CannedResponse $cannedResponse): JsonResponse
    {
        $this->authorizeAccount($request, $account);
        $this->scoped($account, $cannedResponse);
        $cannedResponse->update($this->validated($request, $account, $cannedResponse));

        return response()->json($cannedResponse);
    }

    public function destroy(Request $request, Account $account, CannedResponse $cannedResponse): Response
    {
        $this->authorizeAccount($request, $account);
        $this->scoped($account, $cannedResponse);
        $cannedResponse->delete();

        return response('', 200);
    }

    private function validated(Request $request, Account $account, ?CannedResponse $item = null): array
    {
        return $request->validate(['canned_response' => ['required', 'array'], 'canned_response.short_code' => ['required', 'string', Rule::unique('canned_responses', 'short_code')->where('account_id', $account->id)->ignore($item)], 'canned_response.content' => ['required', 'string']])['canned_response'];
    }

    private function scoped(Account $account, CannedResponse $item): void
    {
        abort_unless($item->account_id === $account->id, 404);
    }

    private function authorizeAccount(Request $request, Account $account): void
    {
        Gate::forUser($request->user())->authorize('view', $account);
    }
}
