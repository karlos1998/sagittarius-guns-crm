<?php

namespace App\Livewire;

use App\Models\Weapon;
use App\Services\WeaponListingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class WeaponList extends Component
{
    public $weapons;
    public $listedWeapons = [];
    public $listingErrors = []; // weaponId => ['error' => '', 'response_file' => '']
    public $isLoggedIn = false;

    protected $listeners = ['refreshWeapons' => '$refresh'];

    public function mount()
    {
        $this->weapons = Weapon::orderBy('created_at', 'desc')->get();
        $this->listedWeapons = Cache::get('listed_weapons', []);

        // Check login status
        $service = new WeaponListingService();
        $this->isLoggedIn = $service->isLoggedIn();
    }

    public function loginToOtobron()
    {
        $service = new WeaponListingService();
        $result = $service->login();

        if ($result['success']) {
            $this->isLoggedIn = true;
            $this->dispatch('login-success', message: $result['message']);
        } else {
            $this->dispatch('login-error', message: $result['message']);
        }
    }

    public function listWeapon($weaponId)
    {
        $service = new WeaponListingService();

        try {
            $result = $service->listWeapon($weaponId);

            if ($result['success'] && $result['published']) {
                // Clear any previous error for this weapon
                unset($this->listingErrors[$weaponId]);

                // Store weapon ID and listing URL in cache
                $this->listedWeapons[$weaponId] = $result['listing_url'] ?? null;

                // Store in cache
                Cache::put('listed_weapons', $this->listedWeapons, now()->addDays(30));

                $this->dispatch('weapon-listed',
                    weaponId: $weaponId,
                    listingUrl: $result['listing_url'] ?? null,
                    responseFile: $result['response_file'] ?? null
                );
            } else {
                // Store error with response file path
                $this->listingErrors[$weaponId] = [
                    'error' => $result['message'] ?? 'Unknown error',
                    'response_file' => $result['response_file'] ?? null,
                ];

                $this->dispatch('weapon-listing-error',
                    weaponId: $weaponId,
                    error: $result['message'] ?? 'Unknown error',
                    responseFile: $result['response_file'] ?? null
                );
            }
        } catch (\Exception $e) {
            // Store error
            $this->listingErrors[$weaponId] = [
                'error' => $e->getMessage(),
                'response_file' => null,
            ];

            $this->dispatch('weapon-listing-error',
                weaponId: $weaponId,
                error: $e->getMessage(),
                responseFile: null
            );
        }
    }

    public function isListed($weaponId)
    {
        return isset($this->listedWeapons[$weaponId]);
    }

    public function getListingUrl($weaponId)
    {
        return $this->listedWeapons[$weaponId] ?? null;
    }

    public function hasError($weaponId)
    {
        return isset($this->listingErrors[$weaponId]);
    }

    public function getError($weaponId)
    {
        return $this->listingErrors[$weaponId] ?? null;
    }

    public function getResponseUrl($weaponId)
    {
        $error = $this->getError($weaponId);
        if (!$error || !$error['response_file']) {
            return null;
        }

        // Convert file path to URL
        // storage/app/otobron_responses/otobron_response_2026-02-05_19-30-21.html
        // -> /otobron-response/otobron_response_2026-02-05_19-30-21.html
        $filename = basename($error['response_file']);
        return url("/otobron-response/{$filename}");
    }

    public function getWeaponImageUrl($photos)
    {
        if (!$photos || !is_array($photos) || count($photos) === 0) {
            return null;
        }

        return Storage::disk('s3')->url($photos[0]);
    }

    public function render()
    {
        return view('livewire.weapon-list');
    }
}
