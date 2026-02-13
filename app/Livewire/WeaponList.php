<?php

namespace App\Livewire;

use App\Models\Weapon;
use App\Services\NetgunListingService;
use App\Services\WeaponListingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class WeaponList extends Component
{
    public $weapons;
    public $listedWeapons = [];
    public $listedWeaponsNetgun = [];
    public $listingErrors = [];
    public $listingErrorsNetgun = [];
    public $isLoggedIn = false;
    public $isLoggedInNetgun = false;

    protected $listeners = ['refreshWeapons' => '$refresh'];

    public function mount()
    {
        $this->weapons = Weapon::orderBy('created_at', 'desc')->get();
        $this->listedWeapons = Cache::get('listed_weapons', []);
        $this->listedWeaponsNetgun = Cache::get('listed_weapons_netgun', []);

        // Check login status for OtoBron
        $service = new WeaponListingService();
        $this->isLoggedIn = $service->isLoggedIn();

        // Check login status for Netgun
        $netgunService = new NetgunListingService();
        $this->isLoggedInNetgun = $netgunService->isLoggedIn();
    }

    // ==================== OTOBRON METHODS ====================

    public function loginToOtobron()
    {
        $service = new WeaponListingService();
        $result = $service->login();

        if ($result['success']) {
            $this->isLoggedIn = true;
            $this->dispatch('login-success', message: $result['message'], platform: 'otobron');
        } else {
            $this->dispatch('login-error', message: $result['message'], platform: 'otobron');
        }
    }

    public function listWeapon($weaponId)
    {
        $service = new WeaponListingService();

        try {
            $result = $service->listWeapon($weaponId);

            if ($result['success'] && $result['published']) {
                unset($this->listingErrors[$weaponId]);
                $this->listedWeapons[$weaponId] = $result['listing_url'] ?? null;
                Cache::put('listed_weapons', $this->listedWeapons, now()->addDays(30));

                $this->dispatch('weapon-listed',
                    weaponId: $weaponId,
                    listingUrl: $result['listing_url'] ?? null,
                    responseFile: $result['response_file'] ?? null,
                    platform: 'otobron'
                );
            } else {
                $this->listingErrors[$weaponId] = [
                    'error' => $result['message'] ?? 'Unknown error',
                    'response_file' => $result['response_file'] ?? null,
                ];

                $this->dispatch('weapon-listing-error',
                    weaponId: $weaponId,
                    error: $result['message'] ?? 'Unknown error',
                    responseFile: $result['response_file'] ?? null,
                    platform: 'otobron'
                );
            }
        } catch (\Exception $e) {
            $this->listingErrors[$weaponId] = [
                'error' => $e->getMessage(),
                'response_file' => null,
            ];

            $this->dispatch('weapon-listing-error',
                weaponId: $weaponId,
                error: $e->getMessage(),
                responseFile: null,
                platform: 'otobron'
            );
        }
    }

    // ==================== NETGUN METHODS ====================

    public function loginToNetgun()
    {
        $service = new NetgunListingService();
        $result = $service->login();

        if ($result['success']) {
            $this->isLoggedInNetgun = true;
            $this->dispatch('login-success', message: $result['message'], platform: 'netgun');
        } else {
            $this->dispatch('login-error', message: $result['message'], platform: 'netgun');
        }
    }

    public function listWeaponNetgun($weaponId)
    {
        $service = new NetgunListingService();

        try {
            $result = $service->listWeapon($weaponId);

            if ($result['success'] && $result['published']) {
                unset($this->listingErrorsNetgun[$weaponId]);
                $this->listedWeaponsNetgun[$weaponId] = $result['listing_url'] ?? null;
                Cache::put('listed_weapons_netgun', $this->listedWeaponsNetgun, now()->addDays(30));

                $this->dispatch('weapon-listed',
                    weaponId: $weaponId,
                    listingUrl: $result['listing_url'] ?? null,
                    responseFile: $result['response_file'] ?? null,
                    platform: 'netgun'
                );
            } else {
                $this->listingErrorsNetgun[$weaponId] = [
                    'error' => $result['message'] ?? 'Unknown error',
                    'response_file' => $result['response_file'] ?? null,
                ];

                $this->dispatch('weapon-listing-error',
                    weaponId: $weaponId,
                    error: $result['message'] ?? 'Unknown error',
                    responseFile: $result['response_file'] ?? null,
                    platform: 'netgun'
                );
            }
        } catch (\Exception $e) {
            $this->listingErrorsNetgun[$weaponId] = [
                'error' => $e->getMessage(),
                'response_file' => null,
            ];

            $this->dispatch('weapon-listing-error',
                weaponId: $weaponId,
                error: $e->getMessage(),
                responseFile: null,
                platform: 'netgun'
            );
        }
    }

    // ==================== HELPER METHODS ====================

    public function isListed($weaponId)
    {
        return isset($this->listedWeapons[$weaponId]);
    }

    public function isListedNetgun($weaponId)
    {
        return isset($this->listedWeaponsNetgun[$weaponId]);
    }

    public function getListingUrl($weaponId)
    {
        return $this->listedWeapons[$weaponId] ?? null;
    }

    public function getListingUrlNetgun($weaponId)
    {
        return $this->listedWeaponsNetgun[$weaponId] ?? null;
    }

    public function hasError($weaponId)
    {
        return isset($this->listingErrors[$weaponId]);
    }

    public function hasErrorNetgun($weaponId)
    {
        return isset($this->listingErrorsNetgun[$weaponId]);
    }

    public function getError($weaponId)
    {
        return $this->listingErrors[$weaponId] ?? null;
    }

    public function getErrorNetgun($weaponId)
    {
        return $this->listingErrorsNetgun[$weaponId] ?? null;
    }

    public function getResponseUrl($weaponId)
    {
        $error = $this->getError($weaponId);
        if (!$error || !$error['response_file']) {
            return null;
        }

        $filename = basename($error['response_file']);
        return url("/otobron-response/{$filename}");
    }

    public function getResponseUrlNetgun($weaponId)
    {
        $error = $this->getErrorNetgun($weaponId);
        if (!$error || !$error['response_file']) {
            return null;
        }

        $filename = basename($error['response_file']);
        return url("/netgun-response/{$filename}");
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
