<?php

namespace App\Services;

class WeaponListingService
{
    /**
     * List a weapon for sale on external platform.
     * This is a placeholder method for future external API integration.
     *
     * @param int $weaponId
     * @return array
     */
    public function listWeapon(int $weaponId): array
    {
        // TODO: Implement external API integration
        // This will be implemented in a later stage when external platform integration is ready

        return [
            'success' => true,
            'message' => 'Weapon listed successfully',
        ];
    }
}
