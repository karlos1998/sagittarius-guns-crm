<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Weapon;

class WeaponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Weapon::create([
            'name' => 'Glock 17',
            'description' => 'Pistolet samopowtarzalny kaliber 9mm. Klasyczny model z doskonałą ergonomią.',
            'price' => 599.99,
            'photos' => ['01KFEDHB8425D8HCYBS3EVZWCN.jpg'], // używam istniejącego zdjęcia
        ]);

        Weapon::create([
            'name' => 'AK-47',
            'description' => 'Legendarny karabin szturmowy. Niezawodny i prosty w obsłudze.',
            'price' => 899.99,
            'photos' => [],
        ]);

        Weapon::create([
            'name' => 'Remington 870',
            'description' => 'Strzelba gładkolufowa pump-action. Idealna do polowań.',
            'price' => 349.99,
            'photos' => [],
        ]);
    }
}
