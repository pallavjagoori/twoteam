<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CsatSurveyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CsatSurveyController extends Controller
{
    public function issue(Request $request, Account $account, int $conversation): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $account);
        $item = $account->conversations()->where('display_id', $conversation)->firstOrFail();
        $survey = $item->csatSurveyResponse()->firstOrCreate([], ['account_id' => $account->id, 'uuid' => Str::uuid()]);

        return response()->json($this->payload($survey));
    }

    public function show(CsatSurveyResponse $survey): JsonResponse
    {
        return response()->json(['csat_survey_response' => $this->payload($survey)]);
    }

    public function update(Request $request, CsatSurveyResponse $survey): JsonResponse
    {
        abort_if($survey->responded_at, 409, 'Survey already submitted');
        $data = $request->validate(['csat_survey_response.rating' => ['required', 'integer', 'between:1,5'], 'csat_survey_response.feedback_message' => ['nullable', 'string', 'max:5000']])['csat_survey_response'];
        $survey->update($data + ['responded_at' => now()]);

        return response()->json(['csat_survey_response' => $this->payload($survey)]);
    }

    public function index(Request $request, Account $account): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $account);

        return response()->json(['payload' => $account->csatSurveyResponses()->whereNotNull('responded_at')->orderByDesc('id')->get()->map(fn ($survey) => $this->payload($survey))]);
    }

    public function metrics(Request $request, Account $account): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $account);
        $query = $account->csatSurveyResponses()->whereNotNull('responded_at');

        return response()->json(['total_count' => $query->count(), 'average_rating' => round((float) $query->avg('rating'), 2), 'ratings' => $query->selectRaw('rating, count(*) as count')->groupBy('rating')->pluck('count', 'rating')]);
    }

    public function download(Request $request, Account $account)
    {
        Gate::forUser($request->user())->authorize('view', $account);
        $lines = ['conversation_id,rating,feedback_message'];
        foreach ($account->csatSurveyResponses()->whereNotNull('responded_at')->get() as $survey) {
            $lines[] = implode(',', [$survey->conversation_id, $survey->rating, str_replace(',', ' ', $survey->feedback_message)]);
        }

return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/csv']);
    }

    private function payload(CsatSurveyResponse $survey): array
    {
        return ['id' => $survey->id, 'uuid' => $survey->uuid, 'conversation_id' => $survey->conversation_id, 'rating' => $survey->rating, 'feedback_message' => $survey->feedback_message, 'responded_at' => $survey->responded_at?->toISOString()];
    }
}
