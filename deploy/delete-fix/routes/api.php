<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\SubjectController;
use App\Http\Controllers\API\StudyLogController;
use App\Http\Controllers\API\FileController;
use App\Http\Controllers\API\SummaryController;
use App\Http\Controllers\API\QuizController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\AIController;
use App\Http\Controllers\API\GoalController;
use App\Http\Controllers\API\GmailController;
use App\Http\Controllers\API\StudyCalendarEventController;

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\MoodController;

/*
|--------------------------------------------------------------------------
| AUTH (ไม่ต้องใช้ token)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('google', [AuthController::class, 'googleSignIn']);
    Route::post('dev-login', [AuthController::class, 'devLogin']);
});

/*
|--------------------------------------------------------------------------
| API ที่ต้องมี token
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'require.bearer'])
    ->scopeBindings()
    ->group(function () {

        /*
        |-------------------------
        | User
        |-------------------------
        */
        Route::get('whoami', function () {
            return response()->json([
                'id'       => request()->user()->id,
                'email'    => request()->user()->email,
                'name'     => request()->user()->name,
                'token_id' => optional(request()->user()->currentAccessToken())->id,
            ]);
        });

        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);

        /*
        |-------------------------
        | Subjects (สำคัญ)
        |-------------------------
        | ✅ มี DELETE /api/subjects/{subject} อัตโนมัติ
        */
        Route::apiResource('subjects', SubjectController::class);
        // Fallback สำหรับโฮสต์ที่ไม่อนุญาต DELETE
        Route::post('subjects/{subject}', [SubjectController::class, 'destroy']);
        Route::post('subjects/{subject}/delete', [SubjectController::class, 'destroy']);



        /*
        |-------------------------
        | Study Logs (nested)
        |-------------------------
        */
        Route::apiResource('subjects.study-logs', StudyLogController::class);

        Route::post('study-logs/{study_log}/files', [FileController::class, 'store']);
        Route::delete('files/{file}', [FileController::class, 'destroy']);

        Route::post('study-logs/{study_log}/summaries', [SummaryController::class, 'generate']);
        Route::get('study-logs/{study_log}/summaries', [SummaryController::class, 'index']);

        /*
        |-------------------------
        | Quiz
        |-------------------------
        */
        Route::get('subjects/{subject}/quizzes', [QuizController::class, 'index']);
        Route::post('subjects/{subject}/quizzes', [QuizController::class, 'store']);
        Route::post('subjects/{subject}/quizzes/from-file', [QuizController::class, 'storeFromFile']);

        Route::get('quizzes/{quiz}', [QuizController::class, 'show']);
        Route::post('quizzes/{quiz}/attempts', [QuizController::class, 'submitAttempt']);

        /*
        |-------------------------
        | Dashboard
        |-------------------------
        */
        Route::get('dashboard/overview', [DashboardController::class, 'overview']);
        Route::get('dashboard/progress', [DashboardController::class, 'progress']);

        /*
        |-------------------------
        | AI
        |-------------------------
        */
        Route::post('ai/analyze/audio', [AIController::class, 'transcribeAudio']);
        Route::post('ai/analyze/document', [AIController::class, 'extractDocument']);
        Route::post('ai/summarize/document', [AIController::class, 'summarizeDocument']);
        Route::post('ai/summarize/audio', [AIController::class, 'summarizeAudio']);
        Route::get('ai/summaries/audio', [AIController::class, 'audioSummaries']);

        /*
        |-------------------------
        | Gmail
        |-------------------------
        */
        Route::get('gmail/authorize', [GmailController::class, 'authorizeGmail']);
        Route::get('gmail/status', [GmailController::class, 'status']);

        /*
        |-------------------------
        | Goals
        |-------------------------
        */
        Route::get('goals/summary', [GoalController::class, 'summary']);
        Route::post('goals/targets', [GoalController::class, 'upsertTarget']);

        /*
        |-------------------------
        | Calendar Events
        |-------------------------
        */
        Route::apiResource('calendar-events', StudyCalendarEventController::class)
            ->parameters(['calendar-events' => 'calendar_event'])
            ->only(['index', 'store', 'update', 'destroy']);

        /*
        |-------------------------
        | Notifications
        |-------------------------
        */
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/settings', [NotificationController::class, 'settings']);
            Route::put('/settings', [NotificationController::class, 'updateSettings']);
            Route::post('/', [NotificationController::class, 'store']);
            Route::post('/schedule', [NotificationController::class, 'createScheduleNotification']);
            Route::post('/schedule-range', [NotificationController::class, 'createScheduleRange']);
            Route::get('/unread', [NotificationController::class, 'unread']);
            Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead']);
            Route::patch('/{notification}/time', [NotificationController::class, 'updateTime']);
            Route::delete('/{notification}', [NotificationController::class, 'destroy']);
        });

        /*
        |-------------------------
        | Mood
        |-------------------------
        */
        Route::prefix('mood')->group(function () {
            Route::get('/', [MoodController::class, 'index']);
            Route::post('/', [MoodController::class, 'store']);
            Route::get('/analytics', [MoodController::class, 'analytics']);
        });
    });

/*
|--------------------------------------------------------------------------
| Gmail Callback (external)
|--------------------------------------------------------------------------
*/
Route::get('gmail/callback', [GmailController::class, 'callback']);
