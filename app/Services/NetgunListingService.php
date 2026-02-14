<?php

namespace App\Services;

use App\Models\Weapon;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NetgunListingService
{
    const CACHE_KEY_COOKIES = 'netgun_session_cookies';
    const CACHE_KEY_XSRF_TOKEN = 'netgun_xsrf_token';

    protected Client $client;
    protected array $config;

    public function __construct()
    {
        $this->config = config('netgun');
        $this->client = new Client([
            'base_uri' => $this->config['base_url'],
            'allow_redirects' => false,
            'timeout' => 30,
        ]);
    }

    /**
     * Login to netgun.pl and store session cookies and XSRF token
     *
     * @return array
     */
    public function login(): array
    {
        try {
            // Create a CookieJar to maintain session state
            $cookieJar = new CookieJar();

            // Step 1: Visit homepage to initialize session
            $this->client->get('/', [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'pl,en-US;q=0.9,en;q=0.8',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Referer' => $this->config['base_url'] . '/',
                ],
                'cookies' => $cookieJar,
            ]);

            // Step 2: GET login page with session cookies
            $loginPageResponse = $this->client->get('/login', [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'pl,en-US;q=0.9,en;q=0.8',
                    'Cache-Control' => 'max-age=0',
                    'Referer' => $this->config['base_url'] . '/',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                ],
                'cookies' => $cookieJar,
            ]);

            // Get body content
            $body = $loginPageResponse->getBody()->getContents();

            // Extract XSRF token from HTML form first (most reliable)
            $xsrfToken = null;
            if (preg_match('/<input[^>]*name="_token"[^>]*value="([^"]+)"/', $body, $matches)) {
                $xsrfToken = $matches[1];
            } elseif (preg_match('/name="_token"\s+value="([^"]+)"/', $body, $matches)) {
                $xsrfToken = $matches[1];
            }

            // Fallback to cookie if not found in HTML
            if (empty($xsrfToken)) {
                foreach ($cookieJar->toArray() as $cookie) {
                    if ($cookie['Name'] === 'XSRF-TOKEN') {
                        $xsrfToken = urldecode($cookie['Value']);
                        break;
                    }
                }
            }

            if (empty($xsrfToken)) {
                return [
                    'success' => false,
                    'message' => 'Nie udało się uzyskać tokenu XSRF',
                ];
            }

            // Step 3: POST login credentials with cookies
            $loginResponse = $this->client->post('/login', [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'pl,en-US;q=0.9,en;q=0.8',
                    'Cache-Control' => 'max-age=0',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Origin' => $this->config['base_url'],
                    'Referer' => $this->config['base_url'] . '/login',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                ],
                'form_params' => [
                    '_token' => $xsrfToken,
                    'email' => $this->config['username'],
                    'password' => $this->config['password'],
                    'remember' => 'on',
                ],
                'cookies' => $cookieJar,
                'allow_redirects' => false,
            ]);

            // Check if login was successful (302 redirect)
            $statusCode = $loginResponse->getStatusCode();
            if ($statusCode !== 302) {
                return [
                    'success' => false,
                    'message' => 'Logowanie nie powiodło się (status: ' . $statusCode . ')',
                ];
            }

            // Extract cookies from CookieJar to string format
            $cookies = $this->cookieJarToString($cookieJar);

            // Also get new XSRF token from response if present
            $newXsrfToken = null;
            foreach ($cookieJar->toArray() as $cookie) {
                if ($cookie['Name'] === 'XSRF-TOKEN') {
                    $newXsrfToken = urldecode($cookie['Value']);
                    break;
                }
            }
            if (!empty($newXsrfToken)) {
                $xsrfToken = $newXsrfToken;
            }

            if (empty($cookies)) {
                return [
                    'success' => false,
                    'message' => 'Nie udało się uzyskać cookies z odpowiedzi logowania',
                ];
            }

            // Store cookies and token in cache (30 days)
            Cache::put(self::CACHE_KEY_COOKIES, $cookies, now()->addDays(30));
            Cache::put(self::CACHE_KEY_XSRF_TOKEN, $xsrfToken, now()->addDays(30));

            Log::info('Successfully logged in to netgun.pl', [
                'cookies' => $cookies,
                'xsrf_token' => $xsrfToken,
            ]);

            return [
                'success' => true,
                'message' => 'Zalogowano pomyślnie do netgun.pl',
                'cookies' => $cookies,
                'xsrf_token' => $xsrfToken,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to login to netgun.pl: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Błąd podczas logowania do netgun.pl: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Convert CookieJar to string format for storage
     *
     * @param CookieJar $cookieJar
     * @return string
     */
    protected function cookieJarToString(CookieJar $cookieJar): string
    {
        $cookies = [];
        foreach ($cookieJar->toArray() as $cookie) {
            $cookies[] = $cookie['Name'] . '=' . $cookie['Value'];
        }
        return implode('; ', $cookies);
    }

    /**
     * Merge cookies from multiple sources, keeping latest values
     *
     * @param string ...$cookieStrings
     * @return string
     */
    protected function mergeCookies(string ...$cookieStrings): string
    {
        $merged = [];

        foreach ($cookieStrings as $cookieString) {
            if (empty($cookieString)) {
                continue;
            }

            $cookies = explode('; ', $cookieString);
            foreach ($cookies as $cookie) {
                if (strpos($cookie, '=') !== false) {
                    list($name, $value) = explode('=', $cookie, 2);
                    $merged[trim($name)] = trim($value);
                }
            }
        }

        $result = [];
        foreach ($merged as $name => $value) {
            $result[] = "{$name}={$value}";
        }

        return implode('; ', $result);
    }

    /**
     * Extract XSRF token from Set-Cookie headers
     *
     * @param array $setCookieHeaders
     * @return string|null
     */
    protected function extractXsrfToken(array $setCookieHeaders): ?string
    {
        foreach ($setCookieHeaders as $header) {
            if (preg_match('/XSRF-TOKEN=([^;]+)/', $header, $matches)) {
                return urldecode($matches[1]);
            }
        }
        return null;
    }

    /**
     * Extract cookies from Set-Cookie headers
     *
     * @param array $setCookieHeaders
     * @return string
     */
    protected function extractCookiesFromHeaders(array $setCookieHeaders): string
    {
        $cookies = [];

        foreach ($setCookieHeaders as $header) {
            if (preg_match('/^([^=]+)=([^;]+)/', $header, $matches)) {
                $cookieName = $matches[1];
                $cookieValue = $matches[2];
                $cookies[] = "{$cookieName}={$cookieValue}";
            }
        }

        return implode('; ', $cookies);
    }

    /**
     * Get cached cookies
     *
     * @return string
     */
    public function getCachedCookies(): string
    {
        return Cache::get(self::CACHE_KEY_COOKIES, '');
    }

    /**
     * Get cached XSRF token
     *
     * @return string
     */
    public function getCachedXsrfToken(): string
    {
        return Cache::get(self::CACHE_KEY_XSRF_TOKEN, '');
    }

    /**
     * Check if user is logged in
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return !empty($this->getCachedCookies()) && !empty($this->getCachedXsrfToken());
    }

    /**
     * List a weapon for sale on netgun.pl platform
     *
     * @param int $weaponId
     * @return array
     */
    public function listWeapon(int $weaponId): array
    {
        try {
            $weapon = Weapon::findOrFail($weaponId);

            // Get cookies
            $cookies = $this->getCachedCookies();

            if (empty($cookies)) {
                return [
                    'success' => false,
                    'message' => 'Brak sesji. Zaloguj się ponownie.',
                ];
            }

            // Step 1: Visit new listing page to get fresh XSRF token
            // Parse cached cookies and create CookieJar with them
            $cookieJar = new CookieJar();
            foreach (explode('; ', $cookies) as $cookieStr) {
                if (strpos($cookieStr, '=') !== false) {
                    list($name, $value) = explode('=', $cookieStr, 2);
                    $cookieJar->setCookie(new SetCookie([
                        'Name' => trim($name),
                        'Value' => trim($value),
                        'Domain' => 'www.netgun.pl',
                        'Path' => '/',
                    ]));
                }
            }

            Log::info("Step 1: GET /nowe-ogloszenie with CookieJar", [
                'cached_cookies_count' => count(explode('; ', $cookies)),
            ]);

            $listingPageResponse = $this->client->get('/nowe-ogloszenie', [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'pl,en-US;q=0.9,en;q=0.8',
                    'Referer' => $this->config['base_url'] . '/',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                ],
                'cookies' => $cookieJar,
            ]);

            $body = $listingPageResponse->getBody()->getContents();

            // Extract fresh XSRF token from HTML form
            $xsrfToken = null;
            if (preg_match('/<input[^>]*name="_token"[^>]*value="([^"]+)"/', $body, $matches)) {
                $xsrfToken = $matches[1];
                Log::info("XSRF token extracted from HTML input", ['token' => $xsrfToken]);
            } elseif (preg_match('/name="_token"\s+value="([^"]+)"/', $body, $matches)) {
                $xsrfToken = $matches[1];
                Log::info("XSRF token extracted from HTML (alt pattern)", ['token' => $xsrfToken]);
            }

            // Fallback to XSRF-TOKEN cookie from jar
            if (empty($xsrfToken)) {
                foreach ($cookieJar->toArray() as $cookie) {
                    if ($cookie['Name'] === 'XSRF-TOKEN') {
                        $xsrfToken = urldecode($cookie['Value']);
                        Log::info("XSRF token extracted from cookie jar", ['token' => $xsrfToken]);
                        break;
                    }
                }
            }

            if (empty($xsrfToken)) {
                Log::error("Failed to get XSRF token - HTML snippet", [
                    'html_snippet' => substr($body, 0, 500),
                ]);
                return [
                    'success' => false,
                    'message' => 'Nie udało się uzyskać tokenu XSRF ze strony nowego ogłoszenia',
                ];
            }

            // Get final cookies from jar (includes updated session cookies)
            $cookies = $this->cookieJarToString($cookieJar);

            Log::info("Step 2: Cookies from jar after GET", [
                'cookies_length' => strlen($cookies),
                'xsrf_token' => $xsrfToken,
            ]);

            // Step 3: Upload images using same CookieJar
            Log::info("Step 3: Starting image upload", [
                'photos_count' => count($weapon->photos),
            ]);

            $imageUrls = $this->uploadImagesWithJar($weapon->photos, $cookieJar, $xsrfToken);

            Log::info("Image upload completed for weapon {$weaponId}", [
                'uploaded_count' => count($imageUrls),
                'urls' => $imageUrls,
            ]);

            if (empty($imageUrls)) {
                return [
                    'success' => false,
                    'message' => 'Nie udało się przesłać zdjęć na serwer netgun.pl',
                ];
            }

            // Step 4: Create listing using same CookieJar
            Log::info("Step 4: Creating listing", [
                'xsrf_token' => $xsrfToken,
            ]);

            $result = $this->createListingWithJar($weapon, $imageUrls, $cookieJar, $xsrfToken);

            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to list weapon {$weaponId} on netgun.pl: " . $e->getMessage());

            return [
                'success' => false,
                'published' => false,
                'message' => 'Wystąpił błąd podczas wystawiania broni na netgun.pl',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload images to netgun.pl image uploader
     *
     * @param array $photos
     * @param string $cookies
     * @param string $xsrfToken
     * @return array Array of uploaded image URLs
     */
    protected function uploadImages(array $photos, string $cookies, string $xsrfToken): array
    {
        $uploadedUrls = [];
        $maxImages = min(count($photos), 10); // Limit to 10 images

        for ($i = 0; $i < $maxImages; $i++) {
            $photoPath = $photos[$i];
            $imageUrl = $this->uploadSingleImage($photoPath, $cookies, $xsrfToken);

            if ($imageUrl) {
                $uploadedUrls[] = $imageUrl;
            }
        }

        return $uploadedUrls;
    }

    /**
     * Upload images to netgun.pl using CookieJar
     *
     * @param array $photos
     * @param CookieJar $cookieJar
     * @param string $xsrfToken
     * @return array Array of uploaded image URLs
     */
    protected function uploadImagesWithJar(array $photos, CookieJar $cookieJar, string $xsrfToken): array
    {
        $uploadedUrls = [];
        $maxImages = min(count($photos), 10);

        for ($i = 0; $i < $maxImages; $i++) {
            $photoPath = $photos[$i];
            $imageUrl = $this->uploadSingleImageWithJar($photoPath, $cookieJar, $xsrfToken);

            if ($imageUrl) {
                $uploadedUrls[] = $imageUrl;
            }
        }

        return $uploadedUrls;
    }

    /**
     * Upload a single image using CookieJar
     *
     * @param string $photoPath
     * @param CookieJar $cookieJar
     * @param string $xsrfToken
     * @return string|null
     */
    protected function uploadSingleImageWithJar(string $photoPath, CookieJar $cookieJar, string $xsrfToken): ?string
    {
        try {
            $imageContent = Storage::disk('s3')->get($photoPath);

            if (!$imageContent) {
                Log::error("Failed to get image content from S3: {$photoPath}");
                return null;
            }

            $base64Image = 'data:image/jpeg;base64,' . base64_encode($imageContent);

            $response = $this->client->post($this->config['endpoints']['image_upload'], [
                'headers' => [
                    'Accept' => 'application/json, text/plain, */*',
                    'Accept-Language' => 'pl,en-US;q=0.9,en;q=0.8,pl-PL;q=0.7',
                    'Content-Type' => 'application/json;charset=UTF-8',
                    'Origin' => $this->config['base_url'],
                    'Referer' => $this->config['base_url'] . '/nowe-ogloszenie',
                    'Sec-Fetch-Dest' => 'empty',
                    'Sec-Fetch-Mode' => 'cors',
                    'Sec-Fetch-Site' => 'same-origin',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                    'X-CSRF-TOKEN' => $xsrfToken,
                    'X-Requested-With' => 'XMLHttpRequest',
                    'X-XSRF-TOKEN' => $xsrfToken,
                ],
                'json' => [
                    'image' => $base64Image,
                    'name' => '',
                ],
                'cookies' => $cookieJar,
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);

            if (isset($responseBody['file'])) {
                // Use image_base_url (without www) for the image URL in form data
                // This must match exactly what netgun.pl expects
                $imageUrl = $this->config['image_base_url'] . '/uploader/' . $responseBody['file'];
                Log::info("Image uploaded successfully to netgun.pl: {$imageUrl}");
                return $imageUrl;
            }

            Log::error("Unexpected response from netgun.pl image uploader", ['response' => $responseBody]);
            return null;

        } catch (\Exception $e) {
            Log::error("Failed to upload image to netgun.pl: " . $e->getMessage(), [
                'photo_path' => $photoPath,
            ]);
            return null;
        }
    }

    /**
     * Upload a single image to netgun.pl
     *
     * @param string $photoPath
     * @param string $cookies
     * @param string $xsrfToken
     * @return string|null Uploaded image URL or null on failure
     */
    protected function uploadSingleImage(string $photoPath, string $cookies, string $xsrfToken): ?string
    {
        try {
            // Get image content from S3
            $imageContent = Storage::disk('s3')->get($photoPath);

            if (!$imageContent) {
                Log::error("Failed to get image content from S3: {$photoPath}");
                return null;
            }

            // Convert to base64
            $base64Image = 'data:image/jpeg;base64,' . base64_encode($imageContent);

            $response = $this->client->post($this->config['endpoints']['image_upload'], [
                'headers' => [
                    'Accept' => 'application/json, text/plain, */*',
                    'Accept-Language' => 'pl,en-US;q=0.9,en;q=0.8,pl-PL;q=0.7',
                    'Content-Type' => 'application/json;charset=UTF-8',
                    'Cookie' => $cookies,
                    'Origin' => $this->config['base_url'],
                    'Referer' => $this->config['base_url'] . '/nowe-ogloszenie',
                    'Sec-Fetch-Dest' => 'empty',
                    'Sec-Fetch-Mode' => 'cors',
                    'Sec-Fetch-Site' => 'same-origin',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                    'X-CSRF-TOKEN' => $xsrfToken,
                    'X-Requested-With' => 'XMLHttpRequest',
                    'X-XSRF-TOKEN' => $xsrfToken,
                ],
                'json' => [
                    'image' => $base64Image,
                    'name' => '',
                ],
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);

            if (isset($responseBody['file'])) {
                // Use image_base_url (without www) for the image URL in form data
                // This must match exactly what netgun.pl expects
                $imageUrl = $this->config['image_base_url'] . '/uploader/' . $responseBody['file'];
                Log::info("Image uploaded successfully to netgun.pl: {$imageUrl}");
                return $imageUrl;
            }

            Log::error("Unexpected response from netgun.pl image uploader", ['response' => $responseBody]);
            return null;

        } catch (\Exception $e) {
            Log::error("Failed to upload image to netgun.pl: " . $e->getMessage(), [
                'photo_path' => $photoPath,
            ]);
            return null;
        }
    }

    /**
     * Create listing on netgun.pl
     *
     * @param Weapon $weapon
     * @param array $imageUrls
     * @param string $cookies
     * @param string $xsrfToken
     * @return array
     */
    protected function createListing(Weapon $weapon, array $imageUrls, string $cookies, string $xsrfToken): array
    {
        try {
            $formData = $this->buildFormData($weapon, $imageUrls, $xsrfToken);

            Log::info("POST /nowe-ogloszenie form data", [
                '_token' => $formData['_token'],
                'name' => $formData['name'],
                'price' => $formData['price'],
                'images_count' => count($imageUrls),
                'cookies_in_header' => substr($cookies, 0, 200) . '...',
            ]);

            $response = $this->client->post($this->config['endpoints']['new_listing'], [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Language' => 'pl,en-US;q=0.9,en;q=0.8,pl-PL;q=0.7',
                    'Cache-Control' => 'max-age=0',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Cookie' => $cookies,
                    'Origin' => $this->config['base_url'],
                    'Referer' => $this->config['base_url'] . '/nowe-ogloszenie',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'same-origin',
                    'Sec-Fetch-User' => '?1',
                    'Upgrade-Insecure-Requests' => '1',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                ],
                'form_params' => $formData,
                'allow_redirects' => true,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            // Save response for debugging
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "netgun_response_{$timestamp}.html";
            $path = storage_path("app/netgun_responses/{$filename}");

            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $debugContent = "<!-- Status Code: {$statusCode} -->\n";
            $debugContent .= "<!-- Weapon ID: {$weapon->id} -->\n";
            $debugContent .= $body;

            file_put_contents($path, $debugContent);
            Log::info("Netgun response saved to: {$path}");

            // Check if listing was created successfully (redirect to promotion page or listing page)
            $listingUrl = $this->extractListingUrl($body, $response);

            if ($listingUrl || $this->isListingCreated($body)) {
                Log::info("Weapon {$weapon->id} listed successfully on netgun.pl");
                return [
                    'success' => true,
                    'published' => true,
                    'message' => 'Broń została pomyślnie wystawiona na netgun.pl',
                    'weapon_id' => $weapon->id,
                    'listing_url' => $listingUrl,
                    'response_file' => $path,
                ];
            }

            return [
                'success' => false,
                'published' => false,
                'message' => 'Nie udało się wystawić broni na netgun.pl',
                'error' => 'Brak potwierdzenia publikacji w odpowiedzi',
                'response_file' => $path,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to create listing on netgun.pl: " . $e->getMessage());

            return [
                'success' => false,
                'published' => false,
                'message' => 'Błąd podczas tworzenia ogłoszenia na netgun.pl',
                'error' => $e->getMessage(),
                'response_file' => null,
            ];
        }
    }

    /**
     * Create listing using CookieJar
     *
     * @param Weapon $weapon
     * @param array $imageUrls
     * @param CookieJar $cookieJar
     * @param string $xsrfToken
     * @return array
     */
    protected function createListingWithJar(Weapon $weapon, array $imageUrls, CookieJar $cookieJar, string $xsrfToken): array
    {
        try {
            $formData = $this->buildFormData($weapon, $imageUrls, $xsrfToken);

            Log::info("POST /nowe-ogloszenie with CookieJar", [
                '_token' => $formData['_token'],
                'name' => $formData['name'],
                'price' => $formData['price'],
                'images_count' => count($imageUrls),
            ]);

            // Pretty print form data for debugging
            Log::info("Form data being sent to netgun.pl:\n" . json_encode($formData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            // Build form data as query string (for proper array encoding)
            $formQuery = http_build_query($formData, '', '&', PHP_QUERY_RFC3986);
            Log::info("Form query string: " . $formQuery);

            $response = $this->client->post($this->config['endpoints']['new_listing'], [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Language' => 'pl,en-US;q=0.9,en;q=0.8,pl-PL;q=0.7',
                    'Cache-Control' => 'max-age=0',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Origin' => $this->config['base_url'],
                    'Referer' => $this->config['base_url'] . '/nowe-ogloszenie',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'same-origin',
                    'Sec-Fetch-User' => '?1',
                    'Upgrade-Insecure-Requests' => '1',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                ],
                'body' => $formQuery,
                'cookies' => $cookieJar,
                'allow_redirects' => false,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            // Save response for debugging
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "netgun_response_{$timestamp}.html";
            $path = storage_path("app/netgun_responses/{$filename}");

            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $debugContent = "<!-- Status Code: {$statusCode} -->\n";
            $debugContent .= "<!-- Weapon ID: {$weapon->id} -->\n";
            $debugContent .= $body;

            file_put_contents($path, $debugContent);
            Log::info("Netgun response saved to: {$path}");

            // Check for 302 redirect to promotion page
            $locationHeader = $response->getHeader('Location');
            $statusCode = $response->getStatusCode();

            Log::info("POST /nowe-ogloszenie response", [
                'status_code' => $statusCode,
                'location_header' => $locationHeader[0] ?? null,
                'has_redirect' => !empty($locationHeader),
            ]);

            if (!empty($locationHeader) && str_contains($locationHeader[0], '/promowanie-ogloszenia/')) {
                Log::info("Got 302 redirect to promotion page", ['location' => $locationHeader[0]]);

                // Step 5: Confirm promotion (skip payment, just confirm)
                $promotionResult = $this->confirmPromotion($locationHeader[0], $cookieJar);

                if ($promotionResult['success']) {
                    return [
                        'success' => true,
                        'published' => true,
                        'message' => 'Broń została pomyślnie wystawiona na netgun.pl',
                        'weapon_id' => $weapon->id,
                        'listing_url' => $promotionResult['listing_url'] ?? null,
                        'response_file' => $path,
                    ];
                } else {
                    Log::error("Promotion confirmation failed", ['error' => $promotionResult['error'] ?? 'unknown']);
                }
            }

            // Check if listing was created successfully (fallback)
            $listingUrl = $this->extractListingUrl($body, $response);

            if ($listingUrl || $this->isListingCreated($body)) {
                Log::info("Weapon {$weapon->id} listed successfully on netgun.pl");
                return [
                    'success' => true,
                    'published' => true,
                    'message' => 'Broń została pomyślnie wystawiona na netgun.pl',
                    'weapon_id' => $weapon->id,
                    'listing_url' => $listingUrl,
                    'response_file' => $path,
                ];
            }

            return [
                'success' => false,
                'published' => false,
                'message' => 'Nie udało się wystawić broni na netgun.pl',
                'error' => 'Brak potwierdzenia publikacji w odpowiedzi',
                'response_file' => $path,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to create listing on netgun.pl: " . $e->getMessage());

            return [
                'success' => false,
                'published' => false,
                'message' => 'Błąd podczas tworzenia ogłoszenia na netgun.pl',
                'error' => $e->getMessage(),
                'response_file' => null,
            ];
        }
    }

    /**
     * Confirm promotion (skip payment, just confirm)
     *
     * @param string $promotionUrl
     * @param CookieJar $cookieJar
     * @return array
     */
    protected function confirmPromotion(string $promotionUrl, CookieJar $cookieJar): array
    {
        try {
            // Parse announcement_number and announcement_token from URL
            // URL format: https://www.netgun.pl/promowanie-ogloszenia/144073/d46c7e3f612aacd450fb1e1552a10be2
            if (!preg_match('/promowanie-ogloszenia\/(\d+)\/([a-f0-9]+)/', $promotionUrl, $matches)) {
                Log::error("Failed to parse promotion URL", ['url' => $promotionUrl]);
                return ['success' => false, 'error' => 'Invalid promotion URL format'];
            }

            $announcementNumber = $matches[1];
            $announcementToken = $matches[2];

            Log::info("Parsed promotion data", [
                'number' => $announcementNumber,
                'token' => $announcementToken,
            ]);

            // Step 1: GET promotion page to get fresh XSRF token
            $promotionPageResponse = $this->client->get($promotionUrl, [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'pl,en-US;q=0.9,en;q=0.8',
                    'Referer' => $this->config['base_url'] . '/nowe-ogloszenie',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                ],
                'cookies' => $cookieJar,
            ]);

            $body = $promotionPageResponse->getBody()->getContents();

            Log::info("GET promotion page response", [
                'status' => $promotionPageResponse->getStatusCode(),
                'body_length' => strlen($body),
            ]);

            // Extract XSRF token from HTML
            $xsrfToken = null;
            if (preg_match('/<input[^>]*name="_token"[^>]*value="([^"]+)"/', $body, $matches)) {
                $xsrfToken = $matches[1];
                Log::info("XSRF token found in promotion page (input pattern 1)");
            } elseif (preg_match('/name="_token"\s+value="([^"]+)"/', $body, $matches)) {
                $xsrfToken = $matches[1];
                Log::info("XSRF token found in promotion page (input pattern 2)");
            }

            // Save promotion page HTML for debugging
            $promoPath = storage_path('app/netgun_responses/promotion_page_' . $announcementNumber . '.html');
            file_put_contents($promoPath, $body);
            Log::info("Promotion page saved to: {$promoPath}");

            if (empty($xsrfToken)) {
                Log::error("Failed to get XSRF token from promotion page", [
                    'html_snippet' => substr($body, 0, 1000),
                ]);
                return ['success' => false, 'error' => 'No XSRF token on promotion page'];
            }

            Log::info("Got XSRF token from promotion page", ['token' => $xsrfToken]);

            // Step 2: POST to confirm promotion (with przelewy24 as payment gateway)
            $formData = [
                '_token' => $xsrfToken,
                'announcement_number' => $announcementNumber,
                'announcement_token' => $announcementToken,
                'payment_gateway' => 'przelewy24',
            ];

            Log::info("POST /promowanie-ogloszenie data", [
                'form_data' => $formData,
                'referer' => $promotionUrl,
            ]);

            $confirmResponse = $this->client->post('/promowanie-ogloszenia', [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'pl,en-US;q=0.9,en;q=0.8',
                    'Cache-Control' => 'max-age=0',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Origin' => $this->config['base_url'],
                    'Referer' => $promotionUrl,
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'same-origin',
                    'Sec-Fetch-User' => '?1',
                    'Upgrade-Insecure-Requests' => '1',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                ],
                'form_params' => $formData,
                'cookies' => $cookieJar,
                'allow_redirects' => false,
            ]);

            $statusCode = $confirmResponse->getStatusCode();
            $locationHeader = $confirmResponse->getHeader('Location');

            Log::info("POST /promowanie-ogloszenie response", [
                'status_code' => $statusCode,
                'location_header' => $locationHeader[0] ?? null,
            ]);

            // Save confirmation response for debugging
            $confirmBody = $confirmResponse->getBody()->getContents();
            $confirmPath = storage_path('app/netgun_responses/confirmation_' . $announcementNumber . '.html');
            file_put_contents($confirmPath, $confirmBody);
            Log::info("Confirmation response saved to: {$confirmPath}");

            // Build listing URL
            $listingUrl = $this->config['base_url'] . '/ogloszenie/' . $announcementNumber;

            return [
                'success' => true,
                'listing_url' => $listingUrl,
                'status_code' => $statusCode,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to confirm promotion: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build form data for listing creation
     *
     * @param Weapon $weapon
     * @param array $imageUrls
     * @param string $xsrfToken
     * @return array
     */
    protected function buildFormData(Weapon $weapon, array $imageUrls, string $xsrfToken): array
    {
        $config = $this->config;

        // Map category
        $category = $this->mapCategory($weapon->category ?? '');

        // Build description
        $prefix = "Odwiedź również nasz sklep: https://militariaforty.pl\n\n";
        $description = $prefix . ($weapon->description ?? '');

        // Build form data
        $formData = [
            '_token' => $xsrfToken,
            'name' => $weapon->name,
            'transaction_type' => $config['defaults']['transaction_type'],
            'item_state' => $config['defaults']['item_state'],
            'category' => $category,
            'nickname' => $config['location']['nickname'],
            'city' => $config['location']['city'],
            'province' => $config['location']['province'],
            'description' => $description,
            'price' => (int) $weapon->price,
            'phone' => $config['contact']['phone'],
            'email' => $config['contact']['email'],
            'url' => $config['defaults']['url'],
            'terms' => 'on',
        ];

        // Add images - use empty brackets syntax [] for proper form encoding
        // This will be serialized as images[]=url1&images[]=url2
        foreach ($imageUrls as $imageUrl) {
            $formData['images[]'] = $imageUrl;
            $formData['titles[]'] = '';
        }

        return $formData;
    }

    /**
     * Map weapon category to netgun.pl category
     *
     * @param string $category
     * @return string
     */
    protected function mapCategory(string $category): string
    {
        $category = strtolower($category);
        $mapping = $this->config['category_mapping'];

        foreach ($mapping as $key => $value) {
            if (str_contains($category, $key)) {
                return $value;
            }
        }

        // Default category
        return 'pistolety';
    }

    /**
     * Extract listing URL from response
     *
     * @param string $html
     * @param mixed $response
     * @return string|null
     */
    protected function extractListingUrl(string $html, $response): ?string
    {
        // Check for redirect URL in response headers
        $locationHeader = $response->getHeader('Location');
        if (!empty($locationHeader)) {
            $location = $locationHeader[0];
            // If redirect to promotion page, extract announcement ID
            if (preg_match('/promowanie-ogloszenia\/(\d+)/', $location, $matches)) {
                return $this->config['base_url'] . '/ogloszenie/' . $matches[1];
            }
            if (str_starts_with($location, 'http')) {
                return $location;
            }
        }

        // Check for listing URL in HTML
        if (preg_match('/<a[^>]*href="(https:\/\/www\.netgun\.pl\/ogloszenie\/[^"]+)"/', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if listing was successfully created
     *
     * @param string $html
     * @return bool
     */
    protected function isListingCreated(string $html): bool
    {
        // Check for success indicators in HTML
        $successIndicators = [
            'promowanie-ogloszenia',
            'Ogłoszenie zostało dodane',
            'Twoje ogłoszenie zostało opublikowane',
        ];

        foreach ($successIndicators as $indicator) {
            if (str_contains($html, $indicator)) {
                return true;
            }
        }

        return false;
    }
}
