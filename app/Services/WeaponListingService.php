<?php

namespace App\Services;

use App\Models\Weapon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WeaponListingService
{
    /**
     * List a weapon for sale on otobron.pl platform.
     *
     * @param int $weaponId
     * @return array
     */
    public function listWeapon(int $weaponId): array
    {
        try {
            $weapon = Weapon::findOrFail($weaponId);

            // Prepare images
            $coverImage = $this->prepareImageForUpload($weapon->photos[0] ?? null);
            $galleryImages = $this->prepareGalleryImages($weapon->photos);

            // Prepare multipart data
            $multipartData = $this->buildMultipartData($weapon, $coverImage, $galleryImages);

            // Send request to otobron.pl
            $response = $this->sendToOtobron($multipartData);

            if ($response['success']) {
                Log::info("Weapon {$weaponId} listed successfully on otobron.pl");
                return [
                    'success' => true,
                    'message' => 'Broń została pomyślnie wystawiona na otobron.pl',
                    'weapon_id' => $weaponId,
                ];
            }

            return [
                'success' => false,
                'message' => 'Nie udało się wystawić broni',
                'error' => $response['error'] ?? 'Unknown error',
            ];

        } catch (\Exception $e) {
            Log::error("Failed to list weapon {$weaponId}: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Wystąpił błąd podczas wystawiania broni',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Prepare image URL for upload (base64 encoded URL)
     *
     * @param string|null $photoPath
     * @return string|null
     */
    protected function prepareImageForUpload(?string $photoPath): ?string
    {
        if (!$photoPath) {
            return null;
        }

        $imageUrl = Storage::disk('s3')->url($photoPath);
        return 'b64:' . base64_encode($imageUrl);
    }

    /**
     * Prepare gallery images
     *
     * @param array $photos
     * @return array
     */
    protected function prepareGalleryImages(array $photos): array
    {
        $galleryImages = [];

        // Skip first image (it's the cover) and take up to 5 more
        $galleryPhotos = array_slice($photos, 1, 5);

        foreach ($galleryPhotos as $photo) {
            $imageUrl = Storage::disk('s3')->url($photo);
            $galleryImages[] = 'b64:' . base64_encode($imageUrl);
        }

        return $galleryImages;
    }

    /**
     * Build multipart form data
     *
     * @param Weapon $weapon
     * @param string|null $coverImage
     * @param array $galleryImages
     * @return array
     */
    protected function buildMultipartData(Weapon $weapon, ?string $coverImage, array $galleryImages): array
    {
        $config = config('otobron');

        $prefix = "Odwiedź również nasz sklep: https://militariaforty.pl/ \n\n";
        $description = $prefix . ($weapon->description ?? '');

        $data = [
            // Basic weapon info
            ['name' => 'job_title', 'contents' => $weapon->name],
            ['name' => 'job_description', 'contents' => $description],

            // Cover image
            ['name' => 'job_cover', 'contents' => '', 'filename' => ''],
            ['name' => 'current_job_cover', 'contents' => $coverImage ?? ''],

            // Contact info
            ['name' => 'job_email', 'contents' => $config['contact']['email']],
            ['name' => 'job_phone', 'contents' => $config['contact']['phone']],

            // Location
            ['name' => 'job_location[0][address]', 'contents' => $config['location']['address']],
            ['name' => 'job_location[0][lat]', 'contents' => $config['location']['lat']],
            ['name' => 'job_location[0][lng]', 'contents' => $config['location']['lng']],

            // Category and type
            ['name' => 'job_category', 'contents' => (string)$config['category_id']],

            // Price
            ['name' => 'weapon-price', 'contents' => (string)((int)$weapon->price)],

            // Condition
            ['name' => 'stan-techniczny[]', 'contents' => $config['defaults']['condition']],

            // Additional options
            ['name' => 'dodatkowe-opcje[]', 'contents' => $config['defaults']['additional_options'][0]],

            // Hidden fields
            ['name' => 'job_manager_form', 'contents' => 'submit-listing'],
            ['name' => 'job_id', 'contents' => '0'],
            ['name' => 'step', 'contents' => '0'],
            ['name' => 'listing_type', 'contents' => $config['listing_type']],
            ['name' => 'submit_job', 'contents' => 'submit--no-preview'],

            // Empty fields
            ['name' => 'job_video_url', 'contents' => ''],
            ['name' => 'producenci-broni', 'contents' => ''],
            ['name' => 'nazwa-egzemplarza', 'contents' => ''],
            ['name' => 'rodzaj-zaponu', 'contents' => ''],
            ['name' => 'mechanizm-dziaania', 'contents' => ''],
            ['name' => 'rodzaj-kolby', 'contents' => ''],
            ['name' => 'rok-produkcji', 'contents' => ''],
            ['name' => 'pojemno-magazynka', 'contents' => ''],
        ];

        // Add gallery images
        if (empty($galleryImages)) {
            $data[] = ['name' => 'job_gallery[]', 'contents' => '', 'filename' => ''];
        } else {
            foreach ($galleryImages as $galleryImage) {
                $data[] = ['name' => 'current_job_gallery[]', 'contents' => $galleryImage];
            }
        }

        return $data;
    }

    /**
     * Send request to otobron.pl
     *
     * @param array $multipartData
     * @return array
     */
    protected function sendToOtobron(array $multipartData): array
    {
        $config = config('otobron');
        $url = $config['api_url'] . '?listing_type=' . $config['listing_type'];

        try {
            $request = Http::asMultipart()
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'pl,en-US;q=0.9,en;q=0.8',
                    'Cache-Control' => 'max-age=0',
                    'Origin' => 'https://otobron.pl',
                    'Referer' => 'https://otobron.pl/add-listing/?listing_type=bron',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'same-origin',
                    'Sec-Fetch-User' => '?1',
                    'Upgrade-Insecure-Requests' => '1',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                ]);

            // Add cookies if configured
            if (!empty($config['cookies'])) {
                $request->withHeaders(['Cookie' => $config['cookies']]);
            }

            $response = $request->attach($multipartData)
                ->post($url);

            if ($response->successful() || $response->redirect()) {
                return [
                    'success' => true,
                    'status_code' => $response->status(),
                ];
            }

            return [
                'success' => false,
                'error' => "HTTP {$response->status()}: " . $response->body(),
                'status_code' => $response->status(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
