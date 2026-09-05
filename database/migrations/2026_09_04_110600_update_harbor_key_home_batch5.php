<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_templates') || ! Schema::hasTable('marketplace_template_pages') || ! Schema::hasTable('marketplace_template_blocks')) {
            return;
        }

        $template = DB::table('marketplace_templates')->where('slug', 'harbor-key-realty')->first();
        if (! $template) return;

        $quickLinks = [
            ['label'=>'Home','url'=>'/'], ['label'=>'Properties','url'=>'/listings'], ['label'=>'Buy','url'=>'/buyers'],
            ['label'=>'Sell','url'=>'/sellers'], ['label'=>'About Us','url'=>'/about'], ['label'=>'Contact','url'=>'/contact'],
        ];
        $services = [
            ['label'=>'Residential Sales','url'=>'/listings'], ['label'=>'Luxury Properties','url'=>'/listings'],
            ['label'=>'Rentals & Leasing','url'=>'/listings'], ['label'=>'Property Management','url'=>'/contact'],
            ['label'=>'Investment Consulting','url'=>'/contact'], ['label'=>'Relocation Services','url'=>'/contact'],
        ];
        $contactLinks = [
            ['label'=>'+63 912 345 6789','url'=>'tel:+639123456789'], ['label'=>'hello@harborandkey.com','url'=>'mailto:hello@harborandkey.com'],
            ['label'=>'www.harborandkey.com','url'=>'/'], ['label'=>'Mon–Sat · 9:00 AM–6:00 PM','url'=>'/contact'],
        ];
        $officeLinks = [
            ['label'=>'Harbor & Key Realty','url'=>'/contact'], ['label'=>'8F The Waterfront Tower','url'=>'/contact'],
            ['label'=>'Lahug, Cebu City 6000','url'=>'/contact'], ['label'=>'View on Map →','url'=>'/contact'],
        ];

        $footer = [
            'type'=>'mega_footer','brand'=>'Harbor & Key Realty','logo_text'=>'HARBOR & KEY REALTY',
            'description'=>'Connecting you to exceptional properties and experiences across Cebu and beyond.',
            'tagline'=>'Connecting you to exceptional properties and experiences across Cebu and beyond.',
            'copyright'=>'© '.now()->year.' Harbor & Key Realty. All Rights Reserved.',
            'privacy_label'=>'Privacy Policy','privacy_url'=>'/privacy-policy','terms_label'=>'Terms of Use','terms_url'=>'/terms-and-conditions',
            'contact'=>['phone'=>'+63 912 345 6789','email'=>'hello@harborandkey.com','address'=>'8F The Waterfront Tower · Lahug, Cebu City 6000'],
            'social_links'=>[
                ['label'=>'Facebook','url'=>'#'], ['label'=>'Instagram','url'=>'#'], ['label'=>'LinkedIn','url'=>'#'], ['label'=>'YouTube','url'=>'#'],
            ],
            'newsletter'=>['title'=>'Newsletter','text'=>'Be the first to get the latest property listings and news.','placeholder'=>'Enter your email','button_label'=>'SUBSCRIBE','action_url'=>'#'],
            'custom_shell_mode'=>true,
            'custom_style'=>['background_color'=>'#071f3d','text_color'=>'#ffffff','muted_color'=>'#aebdd0','accent_color'=>'#c6a052','logo_tone'=>'light'],
            'mega_footer'=>[
                'enabled'=>true,'variant'=>'primary','theme'=>'primary',
                'tagline'=>'Connecting you to exceptional properties and experiences across Cebu and beyond.',
                'primary_label'=>'BOOK A CONSULTATION','primary_url'=>'/contact',
                'columns'=>[
                    ['title'=>'Quick Links','items'=>$quickLinks], ['title'=>'Our Services','items'=>$services],
                    ['title'=>'Contact Us','items'=>$contactLinks], ['title'=>'Our Office','items'=>$officeLinks],
                ],
            ],
            'columns'=>[
                ['title'=>'Quick Links','links'=>$quickLinks], ['title'=>'Our Services','links'=>$services],
                ['title'=>'Contact Us','links'=>$contactLinks], ['title'=>'Our Office','links'=>$officeLinks],
            ],
            'marketplace_variant'=>'harbor_coastal',
        ];

        DB::table('marketplace_templates')->where('id', $template->id)->update([
            'global_footer'=>json_encode($footer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'version'=>max(6, (int) ($template->version ?? 1)),
            'updated_at'=>now(),
        ]);

        $home = DB::table('marketplace_template_pages')
            ->where('marketplace_template_id', $template->id)
            ->where('is_home', true)
            ->orderBy('sort_order')->orderBy('id')->first();
        if (! $home) return;

        $content = [
            'eyebrow'=>'YOUR NEXT MOVE STARTS HERE',
            'heading'=>'Ready to Find Your Place by the Sea?',
            'text'=>'Let’s work together to find a home that matches your lifestyle and goals.',
            'primary_label'=>'BOOK A CONSULTATION','primary_url'=>'/contact',
            'secondary_label'=>'EXPLORE PROPERTIES','secondary_url'=>'/listings',
            'image_url'=>'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=2200&q=90',
            '_content_brief'=>'Keep this final Harbor & Key homepage CTA as a full-width coastal image strip with deep navy overlay, warm-gold consultation action, and white secondary property action. Luna may personalize copy and media while preserving the purchased design language.',
        ];

        $desired = DB::table('marketplace_template_blocks')->where('marketplace_template_page_id', $home->id)->where('spark_key', 'marketplace_harbor_home_cta')->orderBy('id')->first();
        $atSort = DB::table('marketplace_template_blocks')->where('marketplace_template_page_id', $home->id)->where('sort_order', 9)->first();
        if ($desired && $atSort && $desired->id !== $atSort->id) {
            DB::table('marketplace_template_blocks')->where('id', $atSort->id)->delete();
            $atSort = null;
        }
        $payload = [
            'spark_key'=>'marketplace_harbor_home_cta','sort_order'=>9,
            'content'=>json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'settings'=>json_encode(['theme'=>'auto']),
            'metadata'=>json_encode(['marketplace_batch'=>'harbor-home-batch5','home_only'=>true]),
            'updated_at'=>now(),
        ];
        if ($desired) DB::table('marketplace_template_blocks')->where('id', $desired->id)->update($payload);
        elseif ($atSort) DB::table('marketplace_template_blocks')->where('id', $atSort->id)->update($payload);
        else {
            $payload['marketplace_template_page_id'] = $home->id;
            $payload['created_at'] = now();
            DB::table('marketplace_template_blocks')->insert($payload);
        }
    }

    public function down(): void
    {
        // Marketplace design iterations are intentionally forward-only catalog content migrations.
    }
};
