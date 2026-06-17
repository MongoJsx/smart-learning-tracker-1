<?php

namespace App\Http\Controllers;

use App\Models\MoodLog;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MoodController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => 'nullable|exists:subjects,id',
            'mood' => 'required|string|in:happy,neutral,sad,stressed,motivated',
            'energy_level' => 'required|integer|min:1|max:10',
            'focus_level' => 'required|integer|min:1|max:10',
            'notes' => 'nullable|string',
        ]);

        $subjectId = $validated['subject_id'] ?? null;
        if ($subjectId && ! Subject::where('id', $subjectId)->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'ไม่พบรายวิชาที่เลือก'], 422);
        }

        $moodLog = MoodLog::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($moodLog, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $logs = MoodLog::where('user_id', $request->user()->id)
            ->with('subject')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json($logs);
    }

    public function analytics(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        
        $moodDistribution = MoodLog::where('user_id', $userId)
            ->select('mood', DB::raw('count(*) as count'))
            ->groupBy('mood')
            ->get();

        $averages = MoodLog::where('user_id', $userId)
            ->select(
                DB::raw('AVG(energy_level) as avg_energy'),
                DB::raw('AVG(focus_level) as avg_focus')
            )
            ->first();

        return response()->json([
            'mood_distribution' => $moodDistribution,
            'averages' => $averages,
        ]);
    }
}
