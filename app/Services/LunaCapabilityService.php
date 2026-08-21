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
            'header_footer_design'=>true,
            'theme_and_brand_changes'=>true,
            'posts_updates'=>(bool)($planCapabilities['posts_updates']??false),
            'commerce_store'=>(bool)($planCapabilities['commerce_store']??false),
            'external_actions'=>false,
            'rule'=>'A capability being listed does not mean an action succeeded. Only an execution result can establish success.',
        ];
    }
}
