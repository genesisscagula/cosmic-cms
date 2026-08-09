<?php

namespace App\Cosmic\Pricing;

class ActionPricing
{
    public const ADD_PAGE = 30;
    public const ADD_MENU_ITEM = 10;
    public const GENERATE_PAGE = 50;
    public const GENERATE_WEBSITE = 100;
    public const AI_REWRITE = 10;
    public const SPARK_AI_PERSONALIZE = 20;
    public const TEMPLATE_AI_PERSONALIZE = 50;

    public static function all(): array
    {
        return [
            'add_page' => self::ADD_PAGE,
            'add_menu_item' => self::ADD_MENU_ITEM,
            'generate_page' => self::GENERATE_PAGE,
            'generate_website' => self::GENERATE_WEBSITE,
            'ai_rewrite' => self::AI_REWRITE,
            'spark_ai_personalize' => self::SPARK_AI_PERSONALIZE,
            'template_ai_personalize' => self::TEMPLATE_AI_PERSONALIZE,
        ];
    }
}
