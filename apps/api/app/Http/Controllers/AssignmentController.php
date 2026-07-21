<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssignmentController extends Controller
{
    public function store(Request $request, Account $account, int $conversation): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $account);
        $item = $account->conversations()->where('display_id', $conversation)->firstOrFail();
        if ($request->has('assignee_id')) {
            $assignee = $account->users()->whereKey($request->input('assignee_id'))->first();
            $item->update(['assignee_id' => $assignee?->id]);

            return response()->json($assignee ? ['id' => $assignee->id, 'name' => $assignee->name, 'email' => $assignee->email] : null);
        }
        if ($request->has('team_id')) {
            $team = $account->teams()->whereKey($request->input('team_id'))->first();
            $item->update(['team_id' => $team?->id]);

            return response()->json($team);
        }

        return response()->json(null);
    }
}
