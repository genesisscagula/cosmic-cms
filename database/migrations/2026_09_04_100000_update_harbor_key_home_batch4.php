<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_templates') || ! Schema::hasTable('marketplace_template_pages') || ! Schema::hasTable('marketplace_template_blocks')) return;
        $template = DB::table('marketplace_templates')->where('slug', 'harbor-key-realty')->first();
        if (! $template) return;
        $home = DB::table('marketplace_template_pages')->where('marketplace_template_id', $template->id)->where('is_home', true)->orderBy('sort_order')->orderBy('id')->first();
        if (! $home) return;

        $blocks = [
            ['marketplace_harbor_home_testimonials', 6, [
                'eyebrow'=>'CLIENT LOVE','heading'=>'What Our Clients Say','items'=>[
                    ['quote'=>'Harbor & Key Realty made our home buying journey smooth and stress-free. Their team is professional, responsive, and truly cares.','name'=>'Jasmine Leith','location'=>'Lapu-Lapu City, Cebu','avatar_url'=>'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=240&q=86'],
                    ['quote'=>'We sold our property above market value in just three weeks. Their marketing and local network are unmatched.','name'=>'Ronald S.','location'=>'Cebu City','avatar_url'=>'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=240&q=86'],
                    ['quote'=>'Exceptional service from start to finish. I now enjoy my dream home overlooking the ocean.','name'=>'Michael A.','location'=>'Talisay City','avatar_url'=>'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=240&q=86'],
                ],'_content_brief'=>'Keep this Harbor & Key homepage section as three restrained client-review cards with compact avatars, premium serif headings, and warm-gold quote accents. Luna may rewrite testimonials while preserving the purchased design language.',
            ]],
            ['marketplace_harbor_home_team', 7, [
                'eyebrow'=>'MEET OUR TEAM','heading'=>'The People Behind Your Next Move','items'=>[
                    ['name'=>'Kaye Rivera','role'=>'Real Estate Broker','bio'=>'Specializes in luxury homes & waterfront properties.','image_url'=>'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=600&q=88'],
                    ['name'=>'James Mendoza','role'=>'Senior Property Advisor','bio'=>'Expert in investments and high-value properties.','image_url'=>'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=600&q=88'],
                    ['name'=>'Angela Torres','role'=>'Client Relations Manager','bio'=>'Dedicated to providing a seamless client experience.','image_url'=>'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=600&q=88'],
                    ['name'=>'Mark Lim','role'=>'Leasing Specialist','bio'=>'Helps clients find the perfect rental homes and spaces.','image_url'=>'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=600&q=88'],
                ],'_content_brief'=>'Keep this Harbor & Key homepage team section as four compact image-and-profile cards. Luna may replace people, roles, bios, and portraits while retaining navy, white, cream, and warm-gold styling.',
            ]],
            ['marketplace_harbor_home_insights', 8, [
                'eyebrow'=>'LATEST INSIGHTS','heading'=>'Real Estate Tips & Updates','view_all_label'=>'VIEW ALL ARTICLES','view_all_url'=>'/blog','read_more_label'=>'Read More','items'=>[
                    ['date'=>'May 10, 2024','title'=>'Why Waterfront Homes in Cebu Are a Smart Investment','url'=>'/blog','image_url'=>'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1000&q=88'],
                    ['date'=>'Apr 24, 2024','title'=>'Top 5 Family-Friendly Communities in Cebu to Consider','url'=>'/blog','image_url'=>'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1000&q=88'],
                    ['date'=>'Apr 05, 2024','title'=>'Renting vs. Buying: What’s Best for You in 2024?','url'=>'/blog','image_url'=>'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=1000&q=88'],
                ],'_content_brief'=>'Keep this Harbor & Key homepage latest-insights section as three editorial property cards with large imagery, dates, serif headlines, and subtle text links. Luna may replace posts while preserving the purchased design language.',
            ]],
        ];

        foreach ($blocks as [$sparkKey, $sortOrder, $content]) {
            $desired = DB::table('marketplace_template_blocks')->where('marketplace_template_page_id', $home->id)->where('spark_key', $sparkKey)->orderBy('id')->first();
            $atSort = DB::table('marketplace_template_blocks')->where('marketplace_template_page_id', $home->id)->where('sort_order', $sortOrder)->first();
            if ($desired && $atSort && $desired->id !== $atSort->id) { DB::table('marketplace_template_blocks')->where('id', $atSort->id)->delete(); $atSort = null; }
            $payload = ['spark_key'=>$sparkKey,'sort_order'=>$sortOrder,'content'=>json_encode($content, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),'settings'=>json_encode(['theme'=>'auto']),'metadata'=>json_encode(['marketplace_batch'=>'harbor-home-batch4','home_only'=>true]),'updated_at'=>now()];
            if ($desired) DB::table('marketplace_template_blocks')->where('id', $desired->id)->update($payload);
            elseif ($atSort) DB::table('marketplace_template_blocks')->where('id', $atSort->id)->update($payload);
            else { $payload['marketplace_template_page_id']=$home->id; $payload['created_at']=now(); DB::table('marketplace_template_blocks')->insert($payload); }
        }
        DB::table('marketplace_templates')->where('id',$template->id)->update(['version'=>max(5,(int)($template->version??1)),'updated_at'=>now()]);
    }
    public function down(): void {}
};
