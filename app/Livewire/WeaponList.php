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

    protected $listeners = ['refreshWeapons' => '$refresh'];

    public function mount()
    {
        $this->weapons = Weapon::orderBy('created_at', 'desc')->get();
        $this->listedWeapons = Cache::get('listed_weapons', []);
    }

    public function listWeapon($weaponId)
    {
        $service = new WeaponListingService();

        try {
            $result = $service->listWeapon($weaponId);

            if ($result['success']) {
                // Add to listed weapons array
                $this->listedWeapons[] = $weaponId;

                // Store in cache
                Cache::put('listed_weapons', $this->listedWeapons, now()->addDays(30));

                $this->dispatch('weapon-listed', weaponId: $weaponId, responseFile: $result['response_file'] ?? null);
            } else {
                // Dispatch error with response file path
                $this->dispatch('weapon-listing-error',
                    weaponId: $weaponId,
                    error: $result['message'] ?? 'Unknown error',
                    responseFile: $result['response_file'] ?? null
                );
            }
        } catch (\Exception $e) {
            $this->dispatch('weapon-listing-error',
                weaponId: $weaponId,
                error: $e->getMessage(),
                responseFile: null
            );
        }
    }

    public function isListed($weaponId)
    {
        return in_array($weaponId, $this->listedWeapons);
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
