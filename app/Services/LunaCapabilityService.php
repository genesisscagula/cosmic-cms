<?php

namespace App\Services;

class LunaCapabilityService
{
    public function forPublic(): array
    {
        return [
            'conversation'=>true,
            'product_questions'=>true,
            'navigate_public_pages'=>true,
            'start_trial'=>true,
            'workspace_mutations'=>false,
            'reason'=>'No authenticated workspace is available.',
        ];
    }

    public function forUser($user,array $planCapabilities=[]): array
    {
        return [
            'conversation'=>true,
            'navigate_workspace'=>true,
            'resolve_website_and_page'=>true,
            'open_page_builder'=>true,
            'create_standard_page'=>true,
            'rename_page'=>true,
            'delete_page'=>['available'=>true,'confirmation_required'=>true],
            'builder_design_mutations'=>true,
            'core_builder_actions_v5'=>[
                'pages'=>['create','rename','duplicate','delete','reorder'],
                'sections'=>['add','remove','duplicate','move','reorder','redesign','replace'],
                'elements'=>['update','replace'],
                'repeaters'=>['add','remove','duplicate','reorder','update'],
                'typography'=>['update','configure'],
                'layout'=>['update','configure','move','reorder'],
                'design'=>['update','replace','configure'],
                'header'=>['update','add','remove','reorder','replace'],
                'footer'=>['update','add','remove','reorder','replace'],
                'navigation'=>['add','remove','reorder','update','navigate'],
                'media'=>['inspect','replace','remove','search','generate','configure'],
                'responsive'=>['inspect','update','configure'],
                'inspect'=>['page','section','element','typography','layout','media','header','footer','form','seo','settings'],
                'form'=>['update','add','remove','reorder','configure','recipient'],
                'seo'=>['update','configure','title','meta_description','slug','og_image','canonical_url','indexing'],
                'settings'=>['update','configure','business_name','contact_email','contact_phone','location','address','site_name'],
                'post'=>['create','update','publish','draft','delete','category','tags','featured_image'],
                'commerce'=>['create_product','update_product','delete_product','publish_product','archive_product','price','sale_price','inventory','categories','sku','featured_image','create_category','update_category','delete_category','update_existing_variant','delete_or_disable_existing_variant'],
            ],
            'read_only_inspection_v5'=>true,
            'responsive_actions_v5'=>true,
            'media_actions_v5'=>true,
            'forms_actions_v5'=>true,
            'seo_actions_v5'=>true,
            'site_settings_actions_v5'=>true,
            'posts_actions_v5'=>(bool)($planCapabilities['posts_updates']??false),
            'commerce_actions_v5'=>(bool)($planCapabilities['commerce_store']??false),
            'header_footer_design'=>true,
            'theme_and_brand_changes'=>true,
            'posts_updates'=>(bool)($planCapabilities['posts_updates']??false),
            'commerce_store'=>(bool)($planCapabilities['commerce_store']??false),
            'external_actions'=>false,
            'rule'=>'A capability being listed does not mean an action succeeded. Only an execution result can establish success.',
        ];
    }
}
