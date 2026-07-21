<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Attachment;
use App\Support\AttachmentPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function index(Request $request, Account $account, int $conversation): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $account);
        $item = $account->conversations()->where('display_id', $conversation)->firstOrFail();
        $attachments = Attachment::query()->where('account_id', $account->id)
            ->whereIn('message_id', $item->messages()->select('id'))
            ->with(['message.sender'])->latest('id')->get();

        return response()->json([
            'meta' => ['total_count' => $attachments->count()],
            'payload' => $attachments->map(fn (Attachment $attachment) => AttachmentPayload::make($attachment)),
        ]);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->file_name, [
            'Content-Type' => $attachment->content_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
