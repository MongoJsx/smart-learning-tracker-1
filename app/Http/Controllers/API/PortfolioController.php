<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\PortfolioImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PortfolioController extends Controller
{
    private function normalizePublicStoragePath(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace('\\', '/', trim($value));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $normalized)) {
            return $normalized;
        }

        $normalized = preg_replace('#^/public/storage/#', '/storage/', $normalized) ?? $normalized;
        $normalized = preg_replace('#^public/storage/#', '/storage/', $normalized) ?? $normalized;
        if (! str_starts_with($normalized, '/')) {
            $normalized = '/'.$normalized;
        }
        if (str_starts_with($normalized, '/storage/')) {
            $normalized = '/public'.$normalized;
        }

        $projectSegment = trim((string) basename(base_path()), '/');
        $host = rtrim(request()->getSchemeAndHttpHost(), '/');

        if ($projectSegment !== '' && ! str_starts_with($normalized, '/'.$projectSegment.'/')) {
            $normalized = '/'.$projectSegment.$normalized;
        }

        return $host.$normalized;
    }

    private function normalizePortfolioPayload(array $data): array
    {
        $data['cover_image'] = $this->normalizePublicStoragePath($data['cover_image'] ?? null);
        $data['profile_image'] = $this->normalizePublicStoragePath($data['profile_image'] ?? null);

        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = array_map(function ($image) {
                if (is_array($image)) {
                    $image['image_path'] = $this->normalizePublicStoragePath($image['image_path'] ?? null);
                }
                return $image;
            }, $data['images']);
        }

        return $data;
    }

    public function show(): JsonResponse
    {
        $user = request()->user();

        $portfolio = Portfolio::query()
            ->where('user_id', $user->id)
            ->with([
                'projects',
                'skills',
                'interests',
                'images' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->latest('updated_at')
            ->first();

        if (! $portfolio) {
            return response()->json([
                'id' => null,
                'user_id' => $user->id,
                'title' => $user->name ? ('พอร์ตโฟลิโอของ '.$user->name) : 'พอร์ตโฟลิโอของฉัน',
                'full_name' => null,
                'nickname' => null,
                'description' => null,
                'cover_image' => null,
                'date_of_birth' => $user->date_of_birth,
                'profile_image' => null,
                'age' => null,
                'ethnicity' => null,
                'nationality' => null,
                'religion' => null,
                'family_history' => null,
                'father_name' => null,
                'father_phone' => null,
                'mother_name' => null,
                'mother_phone' => null,
                'education_history' => null,
                'special_abilities' => null,
                'awards_summary' => null,
                'phone' => null,
                'address' => null,
                'theme_color' => '#2563eb',
                'is_public' => true,
                'projects' => [],
                'skills' => [],
                'interests' => [],
                'images' => [],
            ]);
        }

        $data = $this->normalizePortfolioPayload($portfolio->toArray());
        $data['date_of_birth'] = $user->date_of_birth;

        return response()->json($data);
    }

    public function upsert(): JsonResponse
    {
        $user = request()->user();
        $validated = request()->validate([
            'title' => 'required|string|max:255',
            'full_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'cover_image' => 'nullable|string|max:255',
            'profile_image' => 'nullable|string|max:255',
            'age' => 'nullable|integer|min:0|max:150',
            'ethnicity' => 'nullable|string|max:100',
            'nationality' => 'nullable|string|max:100',
            'religion' => 'nullable|string|max:100',
            'family_history' => 'nullable|string',
            'father_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:50',
            'mother_name' => 'nullable|string|max:255',
            'mother_phone' => 'nullable|string|max:50',
            'education_history' => 'nullable|string',
            'special_abilities' => 'nullable|string',
            'awards_summary' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'theme_color' => ['nullable', 'string', 'max:20', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'is_public' => 'nullable|boolean',
            'projects' => 'nullable|array',
            'projects.*.project_name' => 'required|string|max:255',
            'projects.*.project_description' => 'nullable|string',
            'projects.*.project_image' => 'nullable|string|max:255',
            'projects.*.project_url' => 'nullable|string|max:255',
            'projects.*.github_url' => 'nullable|string|max:255',
            'projects.*.technologies' => 'nullable|string',
            'projects.*.project_type' => 'nullable|string|max:100',
            'skills' => 'nullable|array',
            'skills.*.skill_name' => 'required|string|max:100',
            'skills.*.skill_level' => 'nullable|string|max:50',
            'interests' => 'nullable|array',
            'interests.*.interest_name' => 'required|string|max:150',
        ]);

        $portfolio = DB::transaction(function () use ($user, $validated) {
            $dateOfBirth = $validated['date_of_birth'] ?? null;

            if (array_key_exists('date_of_birth', $validated)) {
                $user->date_of_birth = $dateOfBirth;
                $user->save();
            }

            $portfolio = Portfolio::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['title' => $validated['title']]
            );

            $updateData = [
                'title' => $validated['title'],
                'full_name' => $validated['full_name'] ?? null,
                'nickname' => $validated['nickname'] ?? null,
                'description' => $validated['description'] ?? null,
                'cover_image' => $this->normalizePublicStoragePath($validated['cover_image'] ?? null),
                'profile_image' => $this->normalizePublicStoragePath($validated['profile_image'] ?? null),
                'age' => $validated['age'] ?? null,
                'ethnicity' => $validated['ethnicity'] ?? null,
                'nationality' => $validated['nationality'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'family_history' => $validated['family_history'] ?? null,
                'father_name' => $validated['father_name'] ?? null,
                'father_phone' => $validated['father_phone'] ?? null,
                'mother_name' => $validated['mother_name'] ?? null,
                'mother_phone' => $validated['mother_phone'] ?? null,
                'education_history' => $validated['education_history'] ?? null,
                'special_abilities' => $validated['special_abilities'] ?? null,
                'awards_summary' => $validated['awards_summary'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'theme_color' => $validated['theme_color'] ?? '#2563eb',
                'is_public' => array_key_exists('is_public', $validated) ? (bool) $validated['is_public'] : true,
            ];

            $filteredUpdateData = [];
            foreach ($updateData as $column => $value) {
                if (Schema::hasColumn('portfolios', $column)) {
                    $filteredUpdateData[$column] = $value;
                }
            }
            $portfolio->update($filteredUpdateData);

            $portfolio->projects()->delete();
            foreach (($validated['projects'] ?? []) as $project) {
                $portfolio->projects()->create([
                    'project_name' => $project['project_name'],
                    'project_description' => $project['project_description'] ?? null,
                    'project_image' => $project['project_image'] ?? null,
                    'project_url' => $project['project_url'] ?? null,
                    'github_url' => $project['github_url'] ?? null,
                    'technologies' => $project['technologies'] ?? null,
                    'project_type' => $project['project_type'] ?? null,
                ]);
            }

            $portfolio->skills()->delete();
            foreach (($validated['skills'] ?? []) as $skill) {
                $portfolio->skills()->create([
                    'skill_name' => $skill['skill_name'],
                    'skill_level' => $skill['skill_level'] ?? 'beginner',
                ]);
            }

            $portfolio->interests()->delete();
            foreach (($validated['interests'] ?? []) as $interest) {
                $portfolio->interests()->create([
                    'interest_name' => $interest['interest_name'],
                ]);
            }

            $fresh = $portfolio->fresh([
                'projects',
                'skills',
                'interests',
                'images' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ]);
            $data = $this->normalizePortfolioPayload($fresh->toArray());
            $data['date_of_birth'] = $user->date_of_birth;

            return $data;
        });

        return response()->json($portfolio);
    }

    public function images(): JsonResponse
    {
        $user = request()->user();

        $portfolio = Portfolio::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $portfolio) {
            return response()->json([]);
        }

        $images = $portfolio->images()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $payload = $images->map(function (PortfolioImage $image) {
            $row = $image->toArray();
            $row['image_path'] = $this->normalizePublicStoragePath($row['image_path'] ?? null);
            return $row;
        })->all();

        return response()->json($payload);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
            'image_type' => ['nullable', 'in:profile,cover,certificate,activity,project,other'],
            'description' => ['nullable', 'string'],
            'image_name' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $portfolio = Portfolio::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['title' => $user->name ? ('พอร์ตโฟลิโอของ '.$user->name) : 'พอร์ตโฟลิโอของฉัน']
        );

        $file = $request->file('image');
        $storedPath = $file->store('portfolio-images/'.$portfolio->id, 'public');
        $publicPath = '/storage/'.ltrim($storedPath, '/');

        $image = $portfolio->images()->create([
            'image_name' => $validated['image_name'] ?? $file->getClientOriginalName(),
            'image_path' => $publicPath,
            'image_type' => $validated['image_type'] ?? 'other',
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        if (($validated['image_type'] ?? null) === 'profile') {
            $portfolio->update([
                'profile_image' => $publicPath,
                'cover_image' => $publicPath,
            ]);
        }

        $payloadImage = $image->toArray();
        $payloadImage['image_path'] = $this->normalizePublicStoragePath($payloadImage['image_path'] ?? null);

        return response()->json([
            'message' => 'อัปโหลดรูปสำเร็จ',
            'image' => $payloadImage,
        ], 201);
    }

    public function uploadCoverImage(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $portfolio = Portfolio::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['title' => $user->name ? ('พอร์ตโฟลิโอของ '.$user->name) : 'พอร์ตโฟลิโอของฉัน']
        );

        $file = $validated['image'];
        $uploadDir = base_path('uploads/portfolio-cover');
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            return response()->json([
                'message' => 'โฟลเดอร์อัปโหลดไม่พร้อมใช้งาน',
            ], 500);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = Str::uuid()->toString().'.'.$extension;
        $file->move($uploadDir, $filename);
        $relativePath = 'uploads/portfolio-cover/'.$filename;
        $projectSegment = trim((string) basename(base_path()), '/');
        $publicUrl = rtrim($request->getSchemeAndHttpHost(), '/').'/'.$projectSegment.'/'.$relativePath;

        $portfolio->update(['cover_image' => $publicUrl]);

        return response()->json([
            'success' => true,
            'message' => 'อัปโหลดรูปหน้าปกสำเร็จ',
            'cover_image' => $publicUrl,
        ], 201);
    }

    public function deleteImage(PortfolioImage $image): JsonResponse
    {
        $user = request()->user();
        abort_unless($image->portfolio->user_id === $user->id, 403, 'Unauthorized');

        $imagePath = str_replace('\\', '/', (string) $image->image_path);
        if (preg_match('#^https?://#i', $imagePath)) {
            $parsedPath = parse_url($imagePath, PHP_URL_PATH);
            if (is_string($parsedPath)) {
                $imagePath = $parsedPath;
            }
        }
        $storedPath = preg_replace('#^/(?:study/)?public/storage/#', '', $imagePath) ?? '';
        $storedPath = preg_replace('#^/storage/#', '', $storedPath) ?? $storedPath;
        $storedPath = ltrim($storedPath, '/');
        if ($storedPath !== '') {
            Storage::disk('public')->delete($storedPath);
        }

        $portfolio = $image->portfolio;
        $deletedPath = $image->image_path;
        $image->delete();

        if ($portfolio->profile_image === $deletedPath) {
            $portfolio->update(['profile_image' => null]);
        }
        if ($portfolio->cover_image === $deletedPath) {
            $portfolio->update(['cover_image' => null]);
        }

        return response()->json(status: 204);
    }

    public function updateImage(Request $request, PortfolioImage $image): JsonResponse
    {
        $user = $request->user();
        abort_unless($image->portfolio->user_id === $user->id, 403, 'Unauthorized');

        $validated = $request->validate([
            'description' => ['nullable', 'string'],
            'image_name' => ['nullable', 'string', 'max:255'],
        ]);

        $image->update([
            'description' => $validated['description'] ?? null,
            'image_name' => $validated['image_name'] ?? $image->image_name,
        ]);

        $payloadImage = $image->fresh()->toArray();
        $payloadImage['image_path'] = $this->normalizePublicStoragePath($payloadImage['image_path'] ?? null);

        return response()->json([
            'message' => 'อัปเดตข้อมูลรูปสำเร็จ',
            'image' => $payloadImage,
        ]);
    }

    public function imageProxy(Request $request)
    {
        $url = trim((string) $request->query('url', ''));
        if ($url === '') {
            abort(400, 'Missing url');
        }

        $publicPath = null;

        if (preg_match('#^https?://#i', $url)) {
            $parsed = parse_url($url);
            $host = strtolower((string) ($parsed['host'] ?? ''));
            $path = (string) ($parsed['path'] ?? '');
            if (in_array($host, ['localhost', '127.0.0.1'], true) && $path !== '') {
                $projectSegment = trim((string) basename(base_path()), '/');
                if ($projectSegment !== '' && str_starts_with($path, '/'.$projectSegment.'/')) {
                    $path = substr($path, strlen('/'.$projectSegment));
                }
                $publicPath = ltrim($path, '/');
            } else {
                try {
                    $resp = Http::timeout(15)->get($url);
                    if (! $resp->successful()) {
                        abort(404);
                    }
                    return response($resp->body(), 200)->header(
                        'Content-Type',
                        $resp->header('Content-Type', 'application/octet-stream')
                    );
                } catch (\Throwable $e) {
                    abort(404);
                }
            }
        } else {
            $path = str_replace('\\', '/', $url);
            $path = preg_replace('#^/(?:study/)?#', '/', $path) ?? $path;
            $publicPath = ltrim($path, '/');
        }

        if (! $publicPath) {
            abort(404);
        }

        $fullPath = public_path($publicPath);
        if (! is_file($fullPath)) {
            abort(404);
        }

        return response()->file($fullPath);
    }

    public function reorderImages(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*.id' => ['required', 'integer', 'exists:portfolio_images,id'],
            'images.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $imageIds = collect($validated['images'])->pluck('id')->values();
        $ownedImageCount = PortfolioImage::query()
            ->whereIn('id', $imageIds)
            ->whereHas('portfolio', fn ($query) => $query->where('user_id', $user->id))
            ->count();

        if ($ownedImageCount !== $imageIds->count()) {
            return response()->json(['message' => 'มีรูปที่ไม่มีสิทธิ์แก้ไข'], 403);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['images'] as $item) {
                PortfolioImage::query()
                    ->where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json(['message' => 'จัดลำดับรูปเรียบร้อยแล้ว']);
    }
}
