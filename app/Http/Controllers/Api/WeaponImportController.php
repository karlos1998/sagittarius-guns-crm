<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Weapon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WeaponImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->hasValidToken($request)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        $query = Weapon::query()->latest();

        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->string('q')->toString() . '%');
        }

        $weapons = $query->get();

        return response()->json([
            'success' => true,
            'exported_at' => now()->toISOString(),
            'count' => $weapons->count(),
            'photos_count' => $weapons->sum(fn (Weapon $weapon): int => count($weapon->photos ?? [])),
            'photos_disk' => config('weapons.photos_disk'),
            'photos_directory' => config('weapons.photos_directory'),
            'weapons' => $weapons->map(fn (Weapon $weapon): array => $this->weaponPayload($weapon))->values()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->hasValidToken($request)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'replace_existing' => ['nullable', 'boolean'],
            'append' => ['nullable', 'boolean'],
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif'],
        ]);

        $disk = (string) config('weapons.photos_disk');
        $directory = trim((string) config('weapons.photos_directory'), '/');
        $replaceExisting = $request->boolean('replace_existing');
        $append = $request->boolean('append');

        $weapon = Weapon::where('name', $validated['name'])->first();
        $existingPhotos = $weapon?->photos ?? [];

        if (!is_array($existingPhotos)) {
            $existingPhotos = [];
        }

        if ($weapon && $replaceExisting && !$append && count($existingPhotos) > 0) {
            Storage::disk($disk)->delete($existingPhotos);
            $existingPhotos = [];
        }

        $weapon ??= new Weapon();
        $weapon->name = $validated['name'];
        $weapon->description = $validated['description'] ?? $weapon->description;

        if (array_key_exists('price', $validated)) {
            $weapon->price = $validated['price'];
        }

        $storedPhotos = [];

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('photos', []);
        foreach ($files as $file) {
            $storedPhotos[] = $this->storePhoto($file, $validated['name'], $disk, $directory);
        }

        $weapon->photos = array_values(array_merge($existingPhotos, $storedPhotos));
        $weapon->save();

        return response()->json([
            'success' => true,
            'weapon' => [
                'id' => $weapon->id,
                'name' => $weapon->name,
                'photos_count' => count($weapon->photos ?? []),
            ],
            'photos' => $storedPhotos,
        ]);
    }

    protected function storePhoto(UploadedFile $file, string $weaponName, string $disk, string $directory): string
    {
        $nameSlug = Str::slug($weaponName) ?: 'weapon';
        $originalSlug = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'photo';
        $extension = $file->extension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $filename = sprintf('%s-%s-%s.%s', $nameSlug, Str::uuid(), $originalSlug, $extension);

        return Storage::disk($disk)->putFileAs($directory, $file, $filename, [
            'visibility' => 'public',
        ]);
    }

    protected function hasValidToken(Request $request): bool
    {
        $expectedToken = config('weapons.import_token');
        $providedToken = $request->bearerToken();

        return is_string($expectedToken)
            && $expectedToken !== ''
            && hash_equals($expectedToken, (string) $providedToken);
    }

    protected function weaponPayload(Weapon $weapon): array
    {
        $photos = collect($weapon->photos ?? [])
            ->filter(fn ($photo): bool => is_string($photo) && $photo !== '')
            ->map(fn (string $photo): array => [
                'path' => $photo,
                'url' => $this->photoUrl($photo),
            ])
            ->values();

        return [
            'id' => $weapon->id,
            'name' => $weapon->name,
            'description' => $weapon->description,
            'price' => $weapon->price,
            'photo_count' => $photos->count(),
            'cover_photo_url' => $photos->first()['url'] ?? null,
            'photo_urls' => $photos->pluck('url')->values()->all(),
            'photos' => $photos->all(),
            'created_at' => $weapon->created_at?->toISOString(),
            'updated_at' => $weapon->updated_at?->toISOString(),
        ];
    }

    protected function photoUrl(string $photo): ?string
    {
        if (Str::startsWith($photo, ['http://', 'https://'])) {
            return $photo;
        }

        try {
            return Storage::disk(config('weapons.photos_disk'))->url($photo);
        } catch (\Throwable) {
            return null;
        }
    }
}
