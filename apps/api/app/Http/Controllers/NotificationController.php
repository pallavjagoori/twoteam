<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Notification;
use App\Support\NotificationPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function index(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAccount($request, $account);
        $query = $this->query($request, $account);
        $count = (clone $query)->count();
        $unread = (clone $query)->whereNull('read_at')->count();
        $direction = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';
        $notifications = $query->with(['conversation.contact', 'conversation.inbox.channel', 'user'])->orderBy('last_activity_at', $direction)->paginate(15);

        return response()->json(['data' => ['meta' => ['unread_count' => $unread, 'count' => $count, 'current_page' => $notifications->currentPage()], 'payload' => $notifications->map(fn ($item) => NotificationPayload::make($item))]]);
    }

    public function unreadCount(Request $request, Account $account): JsonResponse
    {
        $this->authorizeAccount($request, $account);

        return response()->json($this->query($request, $account)->whereNull('read_at')->count());
    }

    public function update(Request $request, Account $account, Notification $notification): JsonResponse
    {
        $notification = $this->notification($request, $account, $notification);
        $notification->update(['read_at' => now()]);

        return response()->json(NotificationPayload::make($notification));
    }

    public function unread(Request $request, Account $account, Notification $notification): JsonResponse
    {
        $notification = $this->notification($request, $account, $notification);
        $notification->update(['read_at' => null]);

        return response()->json(NotificationPayload::make($notification));
    }

    public function readAll(Request $request, Account $account): Response
    {
        $this->authorizeAccount($request, $account);
        $query = $this->query($request, $account)->whereNull('read_at');
        if ($request->input('primary_actor_type') === 'Conversation' && $request->filled('primary_actor_id')) {
            $query->where('primary_actor_type', 'Conversation')->where('primary_actor_id', $request->integer('primary_actor_id'));
        }
        $query->update(['read_at' => now()]);

        return response('', 200);
    }

    public function snooze(Request $request, Account $account, Notification $notification): JsonResponse
    {
        $notification = $this->notification($request, $account, $notification);
        $data = $request->validate(['snoozed_until' => ['nullable', 'date']]);
        if (isset($data['snoozed_until'])) {
            $notification->update(['snoozed_until' => $data['snoozed_until'], 'meta' => array_merge($notification->meta ?? [], ['last_snoozed_at' => null])]);
        }

        return response()->json(NotificationPayload::make($notification));
    }

    public function destroy(Request $request, Account $account, Notification $notification): Response
    {
        $this->notification($request, $account, $notification)->delete();

        return response('', 200);
    }

    public function destroyAll(Request $request, Account $account): Response
    {
        $this->authorizeAccount($request, $account);
        $query = $this->query($request, $account);
        if ($request->input('type') === 'read') {
            $query->whereNotNull('read_at');
        }
        $query->delete();

        return response('', 200);
    }

    private function query(Request $request, Account $account)
    {
        return Notification::query()->where('account_id', $account->id)->where('user_id', $request->user()->id)->where(fn ($query) => $query->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now()));
    }

    private function notification(Request $request, Account $account, Notification $notification): Notification
    {
        $this->authorizeAccount($request, $account);
        abort_unless($notification->account_id === $account->id && $notification->user_id === $request->user()->id, 404);

        return $notification->load(['conversation.contact', 'conversation.inbox.channel', 'user']);
    }

    private function authorizeAccount(Request $request, Account $account): void
    {
        Gate::forUser($request->user())->authorize('view', $account);
    }
}
