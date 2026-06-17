<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\SummaryResource;
use App\Models\Summary;
use App\Models\StudyLog;
use App\Services\AI\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SummaryController extends Controller
{
    public function __construct(private readonly AIService $aiService)
    {
    }

    public function index(StudyLog $studyLog): AnonymousResourceCollection
    {
        $this->authorizeStudyLog($studyLog);
        return SummaryResource::collection($studyLog->summaries);
    }

    public function generate(StudyLog $studyLog, Request $request): JsonResponse
    {
        $this->authorizeStudyLog($studyLog);

        if ($request->filled('content')) {
            $data = $request->validate([
                'content' => ['required', 'string'],
                'ai_model' => ['nullable', 'string', 'max:255'],
                'metadata' => ['nullable', 'array'],
            ]);

            $summary = $studyLog->summaries()->create([
                'content' => $data['content'],
                'ai_model' => $data['ai_model'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            return response()->json(new SummaryResource($summary), 201);
        }

        $summary = $this->aiService->generateSummary($studyLog);

        return response()->json(new SummaryResource($summary), 201);
    }

    public function destroy(Summary $summary): JsonResponse
    {
        $summary->loadMissing('studyLog.subject');
        abort_unless($summary->studyLog?->subject?->user_id === request()->user()->id, 403, 'Unauthorized');

        $summary->delete();

        return response()->json([
            'message' => 'Summary deleted successfully.',
        ]);
    }

    private function authorizeStudyLog(StudyLog $studyLog): void
    {
        abort_unless($studyLog->subject->user_id === request()->user()->id, 403, 'Unauthorized');
    }
}
