<?php

namespace App\AI\Registries;

class SectionRegistry
{

    public static function all()
    {

        return [

            [

               "slug"=>"hero_headline",

                "title"=>"Hero Headline",

                "category"=>"Hero",

                "description"=>"Large hero with headline and CTA",

                "best_for"=>[
                    "agency",
                    "saas",
                    "clinic"
                ]


            ],

            [

                "slug"=>"feature_image_left",

                "category"=>"About",

                "description"=>"Image left content right",
                
                "best_for"=>[
                    "agency",
                    "clinic",
                    "portfolio"
                ]

            ],

            [

                "slug"=>"services_bento",

                "category"=>"Services",

                "description"=>"Modern services list",
                
                "best_for"=>[
                    "agency",
                    "clinic",
                    "portfolio"
                ]

            ],

            [

                "slug"=>"feature_image_right",

                "category"=>"Features",

                "description"=>"Image right content left",
                
                "best_for"=>[
                    "agency",
                    "clinic",
                    "portfolio"
                ]

            ],

            [

                "slug"=>"hero_centered_cta",

                "category"=>"CTA",

                "description"=>"Centered call to action",
                
                "best_for"=>[
                    "agency",
                    "clinic",
                    "portfolio"
                ]

            ]

        ];

    }

}