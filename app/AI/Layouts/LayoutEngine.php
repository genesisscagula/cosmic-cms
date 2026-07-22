<?php

namespace App\AI\Layouts;

class LayoutEngine
{
    public static function random(string $folder): array
    {
        $layouts = match ($folder) {

            'restaurant' => require __DIR__.'/RestaurantLayouts.php',

            default => require __DIR__.'/DefaultLayouts.php',

        };

        return $layouts[array_rand($layouts)];
    }
}