<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SemesterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(): JsonResponse
    {
        if (! $this->hasTableSafe((new Semester())->getTable())) {
            return response()->json([]);
        }

        $semesters = Semester::query()
            ->orderBy('semester')
            ->orderBy('academic_year')
            ->get();

        $payload = $semesters->map(fn (Semester $semester) => [
            'semester_id' => (int) $semester->semester_id,
            'semester' => (int) $semester->semester,
            'academic_year' => (int) $semester->academic_year,
        ]);

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->hasTableSafe((new Semester())->getTable())) {
            return response()->json([
                'message' => 'ไม่พบตารางภาคเรียนในฐานข้อมูล',
            ], 422);
        }

        $data = $request->validate([
            'semester' => ['required', 'integer', 'min:1', 'max:3'],
            'academic_year' => ['required', 'integer', 'digits:4', 'min:2000', 'max:3000'],
        ]);

        $semesterValue = (int) $data['semester'];
        $academicYearValue = (int) $data['academic_year'];

        $semester = Semester::query()
            ->where('semester', $semesterValue)
            ->where('academic_year', $academicYearValue)
            ->first();

        if (! $semester) {
            try {
                $semester = Semester::query()->create([
                    'semester' => $semesterValue,
                    'academic_year' => $academicYearValue,
                ]);
            } catch (Throwable) {
                $nextId = (int) (DB::table('semester')->max('semester_id') ?? 0) + 1;
                DB::table('semester')->insert([
                    'semester_id' => $nextId,
                    'semester' => $semesterValue,
                    'academic_year' => $academicYearValue,
                ]);

                $semester = Semester::query()->find($nextId);
            }
        }

        return response()->json([
            'semester_id' => (int) $semester->semester_id,
            'semester' => (int) $semester->semester,
            'academic_year' => (int) $semester->academic_year,
        ], 201);
    }

    private function hasTableSafe(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $exists = DB::selectOne(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            ) !== null;
        } catch (Throwable $error) {
            $exists = false;
        }

        $cache[$table] = $exists;
        return $exists;
    }
}
