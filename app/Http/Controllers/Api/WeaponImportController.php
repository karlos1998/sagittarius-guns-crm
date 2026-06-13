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
    public function store(Request $request): JsonResponse
    {
        $expectedToken = config('weapons.import_token');
        $providedToken = $request->bearerToken();

        if (!is_string($expectedToken) || $expectedToken === '' || !hash_equals($expectedToken, (string) $providedToken)) {
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
}
