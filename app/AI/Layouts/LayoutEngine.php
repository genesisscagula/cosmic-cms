<?php

namespace App\AI\Layouts;

class LayoutEngine
{
    public static function random(string $folder): array
    {
        $layoutFiles = [
            'restaurant' => 'RestaurantLayouts.php',
            'coffee' => 'CoffeeLayouts.php',
            'bakery' => 'BakeryLayouts.php',
            'hotel' => 'HotelLayouts.php',
            'travel' => 'TravelLayouts.php',
            'automotive' => 'AutomotiveLayouts.php',
            'construction' => 'ConstructionLayouts.php',
            'electrician' => 'ElectricianLayouts.php',
            'plumbing' => 'PlumbingLayouts.php',
            'roofing' => 'RoofingLayouts.php',
            'dentist' => 'DentistLayouts.php',
            'medical' => 'MedicalLayouts.php',
            'fitness' => 'FitnessLayouts.php',
            'cleaning' => 'CleaningLayouts.php',
            'landscaping' => 'LandscapingLayouts.php',
            'lawyer' => 'LawyerLayouts.php',
            'finance' => 'FinanceLayouts.php',
            'real-estate' => 'RealEstateLayouts.php',
            'technology' => 'TechnologyLayouts.php',
            'education' => 'EducationLayouts.php',
            'salon' => 'SalonLayouts.php',
        ];

        $layoutFile = $layoutFiles[$folder] ?? 'DefaultLayouts.php';
        $layouts = require __DIR__.'/'.$layoutFile;

        return $layouts[array_rand($layouts)];
    }
}
