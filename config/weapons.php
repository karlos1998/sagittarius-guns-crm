<?php

return [
    'photos_disk' => env('WEAPON_PHOTOS_DISK', 's3'),
    'photos_directory' => env('WEAPON_PHOTOS_DIRECTORY', 'weapons'),

    'import_token' => env('WEAPON_IMPORT_TOKEN'),

    'max_upload_kilobytes' => env('WEAPON_PHOTO_MAX_UPLOAD_KB'),
    'max_photos_per_weapon' => env('WEAPON_MAX_PHOTOS_PER_WEAPON'),
];
