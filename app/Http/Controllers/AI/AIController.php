<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AIController extends Controller
{
    public function generatePage(Request $request)
    {
        return response()->json([

            "blocks" => [

                [

                    "type" => "hero_headline",

                    "subtitle" => "WELCOME TO OUR CLINIC",

                    "heading" => "Healthy Smiles For The Whole Family",

                    "text" => "Modern dental care with experienced dentists and state-of-the-art technology.",

                    "btn1_label" => "Book Appointment",

                    "btn1_url" => "#",

                    "btn2_label" => "Our Services",

                    "btn2_url" => "#"

                ],

                [

                    "type" => "feature_image_left",

                    "category" => "ABOUT US",

                    "heading" => "Trusted Dental Professionals",

                    "text" => "We provide comfortable and affordable dental care for children and adults using modern techniques.",

                    "button_label" => "Learn More",

                    "button_url" => "#",

                    "image_url" => "https://picsum.photos/900/600?random=1"

                ],

                [

                    "type" => "services_bento",

                    "tagline" => "OUR SERVICES",

                    "heading" => "Complete Dental Solutions",

                    "description" => "Everything you need for a healthier smile.",

                    "services" => [

                        [

                            "icon" => "🦷",

                            "title" => "General Dentistry",

                            "desc" => "Routine dental care and oral health."

                        ],

                        [

                            "icon" => "😁",

                            "title" => "Teeth Whitening",

                            "desc" => "Professional whitening treatments."

                        ],

                        [

                            "icon" => "🪥",

                            "title" => "Dental Cleaning",

                            "desc" => "Keep your teeth healthy and bright."

                        ]

                    ]

                ],

                [

                    "type" => "feature_image_right",

                    "category" => "WHY CHOOSE US",

                    "heading" => "Comfort Meets Modern Technology",

                    "text" => "Our clinic uses advanced dental equipment to provide fast, painless and accurate treatments.",

                    "button_label" => "Book Today",

                    "button_url" => "#",

                    "image_url" => "https://picsum.photos/900/600?random=2"

                ],

                [

                    "type" => "hero_centered_cta",

                    "tagline" => "READY TO SMILE?",

                    "heading" => "Schedule Your First Visit Today"

                ]

            ]

        ]);
    }
}