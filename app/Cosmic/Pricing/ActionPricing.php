<?php

namespace App\Cosmic\Pricing;

class ActionPricing
{
    public const ADD_PAGE = 3;
    public const ADD_MENU_ITEM = 1;
    public const GENERATE_PAGE = 5;
    public const GENERATE_WEBSITE = 10;
    public const AI_REWRITE = 1;

    public static function all(): array
    {
        return [
            'add_page' => self::ADD_PAGE,
            'add_menu_item' => self::ADD_MENU_ITEM,
            'generate_page' => self::GENERATE_PAGE,
            'generate_website' => self::GENERATE_WEBSITE,
            'ai_rewrite' => self::AI_REWRITE,
        ];
    }
}
