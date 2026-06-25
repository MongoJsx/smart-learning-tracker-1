<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuizAttemptRequest;
use App\Http\Requests\QuizGenerationRequest;
use App\Http\Resources\QuizAnswerResource;
use App\Http\Resources\QuizResource;
use App\Models\FileAttachment;
use App\Models\Quiz;
use App\Models\StudyLog;
use App\Models\Subject;
use App\Services\AI\AIService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuizController extends Controller
{
    public function __construct(private readonly AIService $aiService)
    {
    }

    public function index(Subject $subject): AnonymousResourceCollection
    {
        $this->authorizeSubject($subject);
        $userId = request()->user()->id;
        $quizzes = $subject->quizzes()
            ->with(['answers' => fn ($query) => $query->where('user_id', $userId)])
            ->latest()
            ->get();
        return QuizResource::collection($quizzes);
    }

    public function store(QuizGenerationRequest $request, Subject $subject): JsonResponse
    {
        $this->authorizeSubject($subject);
        $this->ensureQuizQuestionColumnsSupportLongText();

        try {
            $quizDto = $this->aiService->generateQuiz($subject, $request->validated());
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'ไม่สามารถสร้างข้อสอบได้',
            ], 422);
        }

        $quiz = DB::transaction(function () use ($subject, $quizDto) {
            $quiz = $subject->quizzes()->create([
                'title' => $this->limitText((string) ($quizDto['title'] ?? $subject->name), 240),
                'description' => $this->limitNullableText($quizDto['description'] ?? null, 240),
                'ai_model' => $quizDto['model'] ?? null,
                'metadata' => $this->normalizeQuizMetadata($quizDto['metadata'] ?? null),
            ]);

            foreach ($quizDto['questions'] as $question) {
                $quiz->questions()->create($this->formatQuestionPayload($question));
            }

            return $quiz;
        });

        return response()->json(new QuizResource($quiz->load('questions')), 201);
    }

    public function storeFromFile(Subject $subject): JsonResponse
    {
        $this->authorizeSubject($subject);
        $this->ensureQuizQuestionColumnsSupportLongText();
        $usedFallbackQuiz = false;

        $data = request()->validate([
            'file' => ['required', 'file', 'max:51200'],
            'title' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'question_types' => ['nullable', 'array'],
            'question_types.*' => ['in:multiple_choice,true_false,short_answer'],
            'question_count' => ['nullable', 'integer', 'min:3', 'max:50'],
            'extracted_text' => ['nullable', 'string'],
        ]);

        /** @var UploadedFile $file */
        $file = request()->file('file');
        if (! $this->isAllowedDocument($file?->getClientOriginalExtension())) {
            return response()->json([
                'message' => 'รองรับเฉพาะไฟล์ PDF, DOC, DOCX หรือ TXT เท่านั้น',
            ], 422);
        }

        try {
            $text = trim((string) ($data['extracted_text'] ?? ''));
            if ($text === '') {
                $extract = $this->aiService->extractDocumentText($file);
                $text = trim((string) ($extract['text'] ?? ''));
            }

            if ($text === '') {
                throw new \RuntimeException('ไม่สามารถดึงข้อความจากไฟล์ได้');
            }

            $quizDto = $this->aiService->generateQuizFromText($subject, $text, $data);
        } catch (\Throwable $e) {
            $bestEffortText = $this->aiService->extractDocumentTextBestEffort($file);
            if (! is_string($bestEffortText) || trim($bestEffortText) === '') {
                return response()->json([
                    'message' => 'ระบบยังอ่านเนื้อหาจริงจากไฟล์นี้ไม่ได้ จึงยังไม่สร้างแบบฝึกหัด กรุณาใช้ไฟล์ DOCX, TXT หรือ PDF ที่คัดลอกข้อความได้',
                ], 422);
            }
            $quizDto = $this->aiService->generateFallbackQuizFromDocument($subject, $file, $data, $e->getMessage(), $bestEffortText);
            $usedFallbackQuiz = true;
        }

        $storedPath = $file->store('study-files/'.$subject->id, 'public');

        try {
            $quiz = DB::transaction(function () use ($subject, $file, $storedPath, $quizDto) {
                $logTitle = trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $logTitle = $logTitle !== '' ? "แบบฝึกหัดจากไฟล์: {$logTitle}" : 'แบบฝึกหัดจากไฟล์เอกสาร';

                $log = StudyLog::create([
                    'subject_id' => $subject->id,
                    'title' => $logTitle,
                    'note' => 'อัปโหลดไฟล์เพื่อสร้างแบบฝึกหัด',
                    'log_date' => Carbon::now()->toDateString(),
                ]);

                $fileRecord = FileAttachment::create([
                    'study_log_id' => $log->id,
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $storedPath,
                    'file_type' => $this->resolveFileType($file->getClientOriginalExtension()),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);

                $metadata = array_merge($quizDto['metadata'] ?? [], [
                    'source' => 'document',
                    'file_id' => $fileRecord->id,
                    'study_log_id' => $log->id,
                    'file_name' => $file->getClientOriginalName(),
                ]);

                $quiz = $subject->quizzes()->create([
                    'title' => $this->limitText((string) ($quizDto['title'] ?? $subject->name), 240),
                    'description' => $this->limitNullableText($quizDto['description'] ?? null, 240),
                    'ai_model' => $quizDto['model'] ?? null,
                    'metadata' => $this->normalizeQuizMetadata($metadata),
                ]);

                foreach ($quizDto['questions'] as $question) {
                    $quiz->questions()->create($this->formatQuestionPayload($question));
                }

                return $quiz;
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($storedPath);
            return response()->json([
                'message' => $e->getMessage() ?: 'บันทึกข้อสอบจากไฟล์ไม่สำเร็จ',
            ], 422);
        }

        $resource = new QuizResource($quiz->load('questions'));

        return response()->json([
            ...$resource->resolve(request()),
            'message' => $usedFallbackQuiz
                ? (($quizDto['metadata']['fallback_mode'] ?? null) === 'document-best-effort'
                    ? 'สร้างแบบฝึกหัดจากข้อความที่อ่านได้ในเอกสารเรียบร้อยแล้ว'
                    : 'สร้างแบบฝึกหัดเบื้องต้นเรียบร้อยแล้ว เนื่องจากระบบยังอ่านเนื้อหาในเอกสารไม่สำเร็จ')
                : 'สร้างแบบฝึกหัดจากเอกสารเรียบร้อยแล้ว',
        ], 201);
    }

    public function show(Quiz $quiz): QuizResource
    {
        $this->authorizeQuiz($quiz);
        return new QuizResource($quiz->load('questions'));
    }

    public function destroy(Quiz $quiz): JsonResponse
    {
        $this->authorizeQuiz($quiz);

        DB::transaction(function () use ($quiz) {
            DB::table('quiz_attempts')->where('quiz_id', $quiz->id)->delete();
            $quiz->answers()->delete();
            $quiz->questions()->delete();
            $quiz->delete();
        });

        return response()->json(status: 204);
    }

    public function submitAttempt(QuizAttemptRequest $request, Quiz $quiz): JsonResponse
    {
        $this->authorizeQuiz($quiz);
        $data = $request->validated();
        $user = $request->user();

        $result = $this->aiService->gradeQuiz($quiz, $user, $data['answers']);
        $percentage = $result['total'] > 0 ? ($result['score'] / $result['total']) * 100 : 0;

        if (Schema::hasTable('quiz_attempts')) {
            $attemptPayload = [
                'user_id' => $user->id,
                'quiz_id' => $quiz->id,
                'answers' => $this->encodeAttemptAnswers($data['answers']),
                'score' => (int) $result['score'],
                'passed' => $percentage >= 60,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            try {
                DB::table('quiz_attempts')->insert($attemptPayload);
            } catch (\Throwable) {
                $attemptPayload['answers'] = '[]';
                DB::table('quiz_attempts')->insert($attemptPayload);
            }
        }

        return response()->json([
            'score' => $result['score'],
            'total' => $result['total'],
            'answers' => QuizAnswerResource::collection($result['answers']),
        ]);
    }

    private function authorizeSubject(Subject $subject): void
    {
        abort_unless($subject->user_id === request()->user()->id, 403, 'Unauthorized');
    }

    private function authorizeQuiz(Quiz $quiz): void
    {
        abort_unless($quiz->subject->user_id === request()->user()->id, 403, 'Unauthorized');
    }

    private function isAllowedDocument(?string $extension): bool
    {
        if (! $extension) {
            return false;
        }

        return in_array(strtolower($extension), ['pdf', 'doc', 'docx', 'txt'], true);
    }

    private function resolveFileType(?string $extension): string
    {
        return match (strtolower((string) $extension)) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'word',
            'txt' => 'other',
            default => 'other',
        };
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function formatQuestionPayload(array $question): array
    {
        $questionText = trim((string) ($question['question_text'] ?? 'Question'));
        $correctAnswer = trim((string) ($question['correct_answer'] ?? ''));
        $explanation = trim((string) ($question['explanation'] ?? ''));

        return [
            'question_text' => $questionText !== '' ? $questionText : 'Question',
            'question_type' => (string) ($question['question_type'] ?? 'multiple_choice'),
            'options' => $this->normalizeOptions($question['options'] ?? null),
            'correct_answer' => $correctAnswer !== '' ? $correctAnswer : null,
            'explanation' => $explanation !== '' ? $explanation : null,
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>|null
     */
    private function normalizeOptions(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $options = array_values(array_filter(array_map(
            fn ($option) => trim((string) $option),
            $value
        ), static fn (string $option) => $option !== ''));

        return $options !== [] ? $options : null;
    }

    private function encodedLength(array $options): int
    {
        $encoded = json_encode($options, JSON_UNESCAPED_UNICODE);
        return strlen($encoded ?: '[]');
    }

    private function limitText(string $value, int $limit): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed, 'UTF-8') <= $limit) {
            return $trimmed;
        }

        return rtrim(mb_substr($trimmed, 0, $limit, 'UTF-8'));
    }

    private function limitNullableText(mixed $value, int $limit): ?string
    {
        $trimmed = trim((string) ($value ?? ''));
        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed, 'UTF-8') <= $limit) {
            return $trimmed;
        }

        return rtrim(mb_substr($trimmed, 0, $limit, 'UTF-8'));
    }

    private function trimText(string $value, int $limit): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed, 'UTF-8') <= $limit) {
            return $trimmed;
        }

        return rtrim(mb_substr($trimmed, 0, $limit, 'UTF-8'));
    }

    private function trimNullableText(mixed $value, int $limit): ?string
    {
        $trimmed = trim((string) ($value ?? ''));
        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed, 'UTF-8') <= $limit) {
            return $trimmed;
        }

        return rtrim(mb_substr($trimmed, 0, $limit, 'UTF-8'));
    }

    /**
     * @param mixed $metadata
     * @return array<string,mixed>|null
     */
    private function normalizeQuizMetadata(mixed $metadata): ?array
    {
        if (! is_array($metadata)) {
            return null;
        }

        $normalized = [
            'source' => isset($metadata['source']) ? (string) $metadata['source'] : null,
            'difficulty' => isset($metadata['difficulty']) ? (string) $metadata['difficulty'] : null,
            'file_id' => isset($metadata['file_id']) ? (int) $metadata['file_id'] : null,
            'study_log_id' => isset($metadata['study_log_id']) ? (int) $metadata['study_log_id'] : null,
        ];

        if (! empty($metadata['requested_types']) && is_array($metadata['requested_types'])) {
            $normalized['requested_types'] = array_values(array_slice(array_map(
                static fn ($type) => (string) $type,
                $metadata['requested_types']
            ), 0, 3));
        }

        if (! empty($metadata['summary_ids']) && is_array($metadata['summary_ids'])) {
            $normalized['summary_ids'] = array_values(array_slice(array_map('intval', $metadata['summary_ids']), 0, 5));
        }

        if (! empty($metadata['file_name'])) {
            $normalized['file_name'] = $this->limitText((string) $metadata['file_name'], 60);
        }

        $normalized = array_filter($normalized, static fn ($value) => $value !== null && $value !== [] && $value !== '');

        if ($normalized === []) {
            return null;
        }

        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        $maxLength = $this->resolveQuizMetadataColumnLength();
        if ($encoded !== false && strlen($encoded) <= $maxLength) {
            return $normalized;
        }

        unset($normalized['file_name']);
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        if ($encoded !== false && strlen($encoded) <= $maxLength) {
            return $normalized;
        }

        unset($normalized['requested_types'], $normalized['summary_ids']);
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        if ($encoded !== false && strlen($encoded) <= $maxLength) {
            return $normalized;
        }

        return array_filter([
            'source' => $normalized['source'] ?? null,
            'file_id' => $normalized['file_id'] ?? null,
            'study_log_id' => $normalized['study_log_id'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function resolveQuizMetadataColumnLength(): int
    {
        try {
            if (! Schema::hasTable('quizzes') || ! Schema::hasColumn('quizzes', 'metadata')) {
                return 240;
            }

            $column = DB::selectOne(
                'SELECT CHARACTER_MAXIMUM_LENGTH AS max_length
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
                 LIMIT 1',
                ['quizzes', 'metadata']
            );

            $maxLength = (int) ($column->max_length ?? 0);
            return $maxLength > 0 ? max(32, $maxLength - 8) : 240;
        } catch (\Throwable) {
            return 240;
        }
    }

    private function encodeAttemptAnswers(array $answers): string
    {
        $encoded = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return is_string($encoded) && $encoded !== '' ? $encoded : '[]';
    }

    private function ensureQuizQuestionColumnsSupportLongText(): void
    {
        if (! Schema::hasTable('quiz_questions')) {
            return;
        }

        try {
            if (DB::getDriverName() !== 'mysql') {
                return;
            }

            $columns = DB::table('information_schema.columns')
                ->selectRaw('COLUMN_NAME as column_name, DATA_TYPE as data_type, CHARACTER_MAXIMUM_LENGTH as max_length')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'quiz_questions')
                ->whereIn('COLUMN_NAME', ['question_text', 'options', 'correct_answer', 'explanation'])
                ->get()
                ->keyBy('column_name');

            $definitions = [
                'question_text' => 'TEXT NOT NULL',
                'options' => 'LONGTEXT NULL',
                'correct_answer' => 'TEXT NULL',
                'explanation' => 'TEXT NULL',
            ];

            foreach ($definitions as $column => $definition) {
                $meta = $columns->get($column);
                if (! $meta) {
                    continue;
                }

                $dataType = strtolower((string) ($meta->data_type ?? ''));
                $maxLength = $meta->max_length !== null ? (int) $meta->max_length : null;

                $alreadyExpanded = match ($column) {
                    'options' => $dataType === 'longtext',
                    default => in_array($dataType, ['text', 'mediumtext', 'longtext'], true) && $maxLength === null,
                };

                if ($alreadyExpanded) {
                    continue;
                }

                DB::statement("ALTER TABLE `quiz_questions` MODIFY `{$column}` {$definition}");
            }
        } catch (\Throwable) {
            // Keep request flow alive; if schema update cannot run, the original DB error will surface.
        }
    }
}
