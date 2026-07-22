<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function report(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account);
        $data = $this->filters($request);
        $query = $this->query($account, $data);
        $bucket = $data['group_by'] === 'hour' ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d 00:00:00';
        $rows = $query->selectRaw("strftime('{$bucket}', created_at) as bucket, count(*) as aggregate")->groupBy('bucket')->orderBy('bucket')->get();

        return response()->json($rows->map(fn ($row) => ['timestamp' => strtotime($row->bucket), 'value' => $this->metricValue($account, $data['metric'], (int) $row->aggregate, $data)]));
    }

    public function summary(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account);
        $data = $this->filters($request);
        $query = $this->query($account, $data);
        $count = $query->count();
        $messages = $account->messages()->whereBetween('created_at', [$data['since_at'], $data['until_at']]);

        return response()->json(['conversations_count' => $count, 'incoming_messages_count' => (clone $messages)->where('message_type', 0)->count(), 'outgoing_messages_count' => (clone $messages)->where('message_type', 1)->count(), 'resolutions_count' => (clone $query)->where('status', 'resolved')->count(), 'avg_first_response_time' => 0, 'avg_resolution_time' => 0]);
    }

    public function drilldown(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account);
        $data = $this->filters($request);
        $page = max(1, $request->integer('page', 1));
        $perPage = min(100, max(1, $request->integer('per_page', 25)));
        $query = $this->query($account, $data)->with(['contact', 'inbox']);
        $total = $query->count();

        return response()->json(['data' => $query->orderBy('id')->forPage($page, $perPage)->get()->map(fn ($item) => ['id' => $item->display_id, 'status' => $item->status, 'inbox_name' => $item->inbox->name, 'contact_name' => $item->contact->name]), 'meta' => ['current_page' => $page, 'total_count' => $total]]);
    }

    public function live(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account);
        $base = $account->conversations();

        return response()->json(['open' => (clone $base)->where('status', 'open')->count(), 'unattended' => (clone $base)->whereNull('assignee_id')->count(), 'unassigned' => (clone $base)->whereNull('assignee_id')->count(), 'pending' => (clone $base)->where('status', 'pending')->count(), 'resolved' => (clone $base)->where('status', 'resolved')->count()]);
    }

    public function groupedLive(Request $request, Account $account): JsonResponse
    {
        $this->auth($request, $account);
        $group = $request->validate(['group_by' => ['required', 'in:assignee_id,team_id']])['group_by'];

        return response()->json($account->conversations()->selectRaw("{$group} as id, count(*) as conversations_count")->groupBy($group)->orderBy($group)->get());
    }

    public function groupedSummary(Request $request, Account $account, string $type): JsonResponse
    {
        $this->auth($request, $account);
        abort_unless(in_array($type, ['inbox', 'agent', 'team', 'label'], true), 404);
        $data = $this->filters($request);
        $column = match ($type) {
            'inbox' => 'inbox_id', 'agent' => 'assignee_id', 'team' => 'team_id', default => 'status'
        };

        return response()->json($this->query($account, $data)->selectRaw("{$column} as id, count(*) as conversations_count")->groupBy($column)->orderBy($column)->get());
    }

    public function csv(Request $request, Account $account, string $type): Response
    {
        $this->auth($request, $account);
        abort_unless(in_array($type, ['agents', 'conversations_summary', 'labels', 'inboxes', 'teams', 'conversation_traffic'], true), 404);
        $data = $this->filters($request);
        $lines = ['id,status,inbox_id,assignee_id,team_id'];
        foreach ($this->query($account, $data)->orderBy('id')->get() as $item) {
            $lines[] = implode(',', [$item->display_id, $item->status, $item->inbox_id, $item->assignee_id, $item->team_id]);
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/csv']);
    }

    private function filters(Request $request): array
    {
        $data = $request->validate(['metric' => ['sometimes', 'in:conversations_count,incoming_messages_count,outgoing_messages_count,resolutions_count,avg_first_response_time,avg_resolution_time'], 'since' => ['sometimes', 'integer'], 'until' => ['sometimes', 'integer'], 'type' => ['sometimes', 'in:account,inbox,agent,team'], 'id' => ['nullable', 'integer'], 'group_by' => ['sometimes', 'in:day,hour'], 'business_hours' => ['sometimes']]);
        $since = $data['since'] ?? now()->subDays(7)->timestamp;
        $until = $data['until'] ?? now()->timestamp;
        abort_unless($since <= $until, 422);

        return $data + ['metric' => 'conversations_count', 'type' => 'account', 'group_by' => 'day', 'since_at' => date('Y-m-d H:i:s', $since), 'until_at' => date('Y-m-d H:i:s', $until)];
    }

    private function query(Account $account, array $data)
    {
        $query = $account->conversations()->whereBetween('created_at', [$data['since_at'], $data['until_at']]);
        if (($data['type'] ?? 'account') !== 'account' && isset($data['id'])) {
            $query->where(match ($data['type']) {
                'inbox' => 'inbox_id', 'agent' => 'assignee_id', default => 'team_id'
            }, $data['id']);
        }

        return $query;
    }

    private function metricValue(Account $account, string $metric, int $conversationCount, array $data): int
    {
        if ($metric === 'conversations_count') {
            return $conversationCount;
        } if (in_array($metric, ['avg_first_response_time', 'avg_resolution_time'], true)) {
            return 0;
        } if ($metric === 'resolutions_count') {
            return $this->query($account, $data)->where('status', 'resolved')->count();
        }

        return $account->messages()->whereBetween('created_at', [$data['since_at'], $data['until_at']])->where('message_type', $metric === 'incoming_messages_count' ? 0 : 1)->count();
    }

    private function auth(Request $request, Account $account): void
    {
        Gate::forUser($request->user())->authorize('view', $account);
    }
}
