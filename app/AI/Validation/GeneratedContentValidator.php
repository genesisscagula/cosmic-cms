<?php

namespace App\AI\Validation;

final class GeneratedContentValidator
{
    /** @return array{blocks:array<int,array<string,mixed>>,diagnostics:array<string,mixed>} */
    public function validate(array $blocks, array $selectedSparks): array
    {
        $byType = [];
        $discarded = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                $discarded[] = 'non_object_block';
                continue;
            }

            $type = trim((string) ($block['type'] ?? ''));
            if ($type === '' || ! in_array($type, $selectedSparks, true) || isset($byType[$type])) {
                $discarded[] = $type !== '' ? $type : 'missing_type';
                continue;
            }

            $byType[$type] = $block;
        }

        $validated = [];
        $created = [];
        $repairedFields = 0;

        foreach ($selectedSparks as $spark) {
            $block = $byType[$spark] ?? [];
            if ($block === []) {
                $created[] = $spark;
            }

            [$normalized, $repairs] = $this->normalizeBlock($spark, $block);
            $validated[] = $normalized;
            $repairedFields += $repairs;
        }

        return [
            'blocks' => $validated,
            'diagnostics' => [
                'expected_count' => count($selectedSparks),
                'received_count' => count($blocks),
                'final_count' => count($validated),
                'created_blocks' => $created,
                'discarded_blocks' => $discarded,
                'repaired_fields' => $repairedFields,
                'changed' => $created !== [] || $discarded !== [] || $repairedFields > 0,
            ],
        ];
    }

    private function normalizeBlock(string $spark, array $block): array
    {
        $spec = $this->specs()[$spark] ?? [];
        $repairs = 0;

        if (($block['type'] ?? null) !== $spark) {
            $repairs++;
        }
        if (($block['theme'] ?? null) !== 'auto') {
            $repairs++;
        }

        $block['type'] = $spark;
        $block['theme'] = 'auto';

        foreach ($spec as $key => $rule) {
            if (is_array($rule) && isset($rule['items'], $rule['count'])) {
                [$block[$key], $changed] = $this->normalizeItems($block[$key] ?? null, $rule);
                $repairs += $changed;
                continue;
            }

            [$block[$key], $changed] = $this->normalizeValue($block[$key] ?? null, $rule);
            $repairs += $changed ? 1 : 0;
        }

        return [$block, $repairs];
    }

    private function normalizeItems(mixed $value, array $rule): array
    {
        $repairs = 0;
        $items = is_array($value) ? array_values($value) : [];
        if (! is_array($value)) {
            $repairs++;
        }

        $count = (int) $rule['count'];
        if (count($items) > $count) {
            $items = array_slice($items, 0, $count);
            $repairs++;
        }
        while (count($items) < $count) {
            $items[] = [];
            $repairs++;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $item = [];
                $repairs++;
            }
            [$items[$index], $itemRepairs] = $this->normalizeObject($item, $rule['items']);
            $repairs += $itemRepairs;
        }

        return [$items, $repairs];
    }

    private function normalizeObject(array $value, array $spec): array
    {
        $repairs = 0;
        foreach ($spec as $key => $rule) {
            if (is_array($rule) && isset($rule['items'], $rule['count'])) {
                [$value[$key], $changed] = $this->normalizeItems($value[$key] ?? null, $rule);
                $repairs += $changed;
                continue;
            }
            [$value[$key], $changed] = $this->normalizeValue($value[$key] ?? null, $rule);
            $repairs += $changed ? 1 : 0;
        }
        return [$value, $repairs];
    }

    private function normalizeValue(mixed $value, mixed $rule): array
    {
        if ($rule === 'bool') {
            return [is_bool($value) ? $value : false, ! is_bool($value)];
        }
        if ($rule === 'int') {
            return [is_numeric($value) ? (int) $value : 0, ! is_numeric($value)];
        }
        if ($rule === 'rating') {
            return [5, $value !== 5];
        }
        if ($rule === 'url') {
            $valid = is_string($value) && trim($value) !== '';
            return [$valid ? trim($value) : '#', ! $valid];
        }
        if ($rule === 'image') {
            return ['', $value !== ''];
        }
        if (is_string($rule) && str_starts_with($rule, 'fixed:')) {
            $fixed = substr($rule, 6);
            return [$fixed, $value !== $fixed];
        }
        if ($rule === 'array') {
            return [is_array($value) ? array_values($value) : [], ! is_array($value)];
        }

        return [is_scalar($value) ? (string) $value : '', ! is_scalar($value)];
    }

    private function specs(): array
    {
        $card = ['icon' => 'string', 'title' => 'string', 'desc' => 'string'];

        return [
            'hero_headline' => ['subtitle'=>'string','heading'=>'string','text'=>'string','btn1_label'=>'string','btn1_url'=>'url','btn2_label'=>'string','btn2_url'=>'url'],
            'feature_image_left' => ['category'=>'string','heading'=>'string','text'=>'string','button_label'=>'string','button_url'=>'url','image_url'=>'image'],
            'feature_image_right' => ['category'=>'string','heading'=>'string','text'=>'string','button_label'=>'string','button_url'=>'url','image_url'=>'image'],
            'services_bento' => ['tagline'=>'string','heading'=>'string','description'=>'string','services'=>['count'=>3,'items'=>$card]],
            'services_bento_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','featured_number'=>'string','featured_title'=>'string','featured_text'=>'string','featured_meta'=>'string','service_two_number'=>'string','service_two_title'=>'string','service_two_text'=>'string','service_three_number'=>'string','service_three_title'=>'string','service_three_text'=>'string','service_four_number'=>'string','service_four_title'=>'string','service_four_text'=>'string','service_five_number'=>'string','service_five_title'=>'string','service_five_text'=>'string','proof_value'=>'string','proof_label'=>'string'],
            'services_pricing_comparison' => ['eyebrow'=>'string', 'heading'=>'string', 'text'=>'string', 'starter_name'=>'string', 'starter_price'=>'string', 'starter_period'=>'string', 'starter_description'=>'string', 'starter_button_label'=>'string', 'starter_button_url'=>'url', 'growth_name'=>'string', 'growth_price'=>'string', 'growth_period'=>'string', 'growth_description'=>'string', 'growth_button_label'=>'string', 'growth_button_url'=>'url', 'growth_badge'=>'string', 'pro_name'=>'string', 'pro_price'=>'string', 'pro_period'=>'string', 'pro_description'=>'string', 'pro_button_label'=>'string', 'pro_button_url'=>'url', 'feature_one'=>'string', 'feature_two'=>'string', 'feature_three'=>'string', 'feature_four'=>'string', 'feature_five'=>'string', 'feature_six'=>'string', 'starter_one'=>'string', 'starter_two'=>'string', 'starter_three'=>'string', 'starter_four'=>'string', 'starter_five'=>'string', 'starter_six'=>'string', 'growth_one'=>'string', 'growth_two'=>'string', 'growth_three'=>'string', 'growth_four'=>'string', 'growth_five'=>'string', 'growth_six'=>'string', 'pro_one'=>'string', 'pro_two'=>'string', 'pro_three'=>'string', 'pro_four'=>'string', 'pro_five'=>'string', 'pro_six'=>'string', 'footnote'=>'string'],
            'services_hover_cards' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','card_one_number'=>'string','card_one_title'=>'string','card_one_summary'=>'string','card_one_text'=>'string','card_one_link'=>'string','card_two_number'=>'string','card_two_title'=>'string','card_two_summary'=>'string','card_two_text'=>'string','card_two_link'=>'string','card_three_number'=>'string','card_three_title'=>'string','card_three_summary'=>'string','card_three_text'=>'string','card_three_link'=>'string','card_four_number'=>'string','card_four_title'=>'string','card_four_summary'=>'string','card_four_text'=>'string','card_four_link'=>'string','card_five_number'=>'string','card_five_title'=>'string','card_five_summary'=>'string','card_five_text'=>'string','card_five_link'=>'string','card_six_number'=>'string','card_six_title'=>'string','card_six_summary'=>'string','card_six_text'=>'string','card_six_link'=>'string'],
            'services_sticky_scroll' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','service_one_number'=>'string','service_one_title'=>'string','service_one_text'=>'string','service_two_number'=>'string','service_two_title'=>'string','service_two_text'=>'string','service_three_number'=>'string','service_three_title'=>'string','service_three_text'=>'string','service_four_number'=>'string','service_four_title'=>'string','service_four_text'=>'string','service_five_number'=>'string','service_five_title'=>'string','service_five_text'=>'string','service_six_number'=>'string','service_six_title'=>'string','service_six_text'=>'string'],
            'services_horizontal' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','service_one_number'=>'string','service_one_title'=>'string','service_one_text'=>'string','service_two_number'=>'string','service_two_title'=>'string','service_two_text'=>'string','service_three_number'=>'string','service_three_title'=>'string','service_three_text'=>'string','service_four_number'=>'string','service_four_title'=>'string','service_four_text'=>'string','service_five_number'=>'string','service_five_title'=>'string','service_five_text'=>'string','service_six_number'=>'string','service_six_title'=>'string','service_six_text'=>'string'],
            'services_interactive_tabs' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','tab_one_label'=>'string','tab_one_title'=>'string','tab_one_text'=>'string','tab_two_label'=>'string','tab_two_title'=>'string','tab_two_text'=>'string','tab_three_label'=>'string','tab_three_title'=>'string','tab_three_text'=>'string','tab_four_label'=>'string','tab_four_title'=>'string','tab_four_text'=>'string'],
            'services_mega_grid' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','item_one_title'=>'string','item_one_text'=>'string','item_two_title'=>'string','item_two_text'=>'string','item_three_title'=>'string','item_three_text'=>'string','item_four_title'=>'string','item_four_text'=>'string','item_five_title'=>'string','item_five_text'=>'string','item_six_title'=>'string','item_six_text'=>'string','item_seven_title'=>'string','item_seven_text'=>'string','item_eight_title'=>'string','item_eight_text'=>'string'],
            'about_timeline_story' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','year_one'=>'string','title_one'=>'string','text_one'=>'string','year_two'=>'string','title_two'=>'string','text_two'=>'string','year_three'=>'string','title_three'=>'string','text_three'=>'string','year_four'=>'string','title_four'=>'string','text_four'=>'string'],
            'about_founder_story' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','quote'=>'string','founder_name'=>'string','founder_role'=>'string','principle_one'=>'string','principle_two'=>'string','principle_three'=>'string','image_url'=>'image','primary_label'=>'string','primary_url'=>'url'],
            'about_mission_grid' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','mission_label'=>'string','mission_title'=>'string','mission_text'=>'string','vision_label'=>'string','vision_title'=>'string','vision_text'=>'string','value_one_title'=>'string','value_one_text'=>'string','value_two_title'=>'string','value_two_text'=>'string','value_three_title'=>'string','value_three_text'=>'string'],
            'about_interactive_stats' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','stat_one_value'=>'string','stat_one_label'=>'string','stat_one_text'=>'string','stat_two_value'=>'string','stat_two_label'=>'string','stat_two_text'=>'string','stat_three_value'=>'string','stat_three_label'=>'string','stat_three_text'=>'string','stat_four_value'=>'string','stat_four_label'=>'string','stat_four_text'=>'string','footnote'=>'string'],
            'about_brand_journey' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','chapter_one_label'=>'string','chapter_one_title'=>'string','chapter_one_text'=>'string','chapter_two_label'=>'string','chapter_two_title'=>'string','chapter_two_text'=>'string','chapter_three_label'=>'string','chapter_three_title'=>'string','chapter_three_text'=>'string','chapter_four_label'=>'string','chapter_four_title'=>'string','chapter_four_text'=>'string','primary_label'=>'string','primary_url'=>'url'],
            'about_awards_timeline' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','award_one_year'=>'string','award_one_title'=>'string','award_one_org'=>'string','award_two_year'=>'string','award_two_title'=>'string','award_two_org'=>'string','award_three_year'=>'string','award_three_title'=>'string','award_three_org'=>'string','award_four_year'=>'string','award_four_title'=>'string','award_four_org'=>'string','footnote'=>'string'],
            'about_culture_section' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','pillar_one_title'=>'string','pillar_one_text'=>'string','pillar_two_title'=>'string','pillar_two_text'=>'string','pillar_three_title'=>'string','pillar_three_text'=>'string','pillar_four_title'=>'string','pillar_four_text'=>'string','closing_line'=>'string'],
            'about_office_gallery' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','image_one_url'=>'image','image_one_caption'=>'string','image_two_url'=>'image','image_two_caption'=>'string','image_three_url'=>'image','image_three_caption'=>'string'],
            'portfolio_masonry' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','project_one_title'=>'string','project_one_meta'=>'string','project_one_image_url'=>'image','project_two_title'=>'string','project_two_meta'=>'string','project_two_image_url'=>'image','project_three_title'=>'string','project_three_meta'=>'string','project_three_image_url'=>'image','project_four_title'=>'string','project_four_meta'=>'string','project_four_image_url'=>'image','project_five_title'=>'string','project_five_meta'=>'string','project_five_image_url'=>'image','project_six_title'=>'string','project_six_meta'=>'string','project_six_image_url'=>'image'],
            'portfolio_pinterest' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','project_one_title'=>'string','project_one_meta'=>'string','project_one_image_url'=>'image','project_two_title'=>'string','project_two_meta'=>'string','project_two_image_url'=>'image','project_three_title'=>'string','project_three_meta'=>'string','project_three_image_url'=>'image','project_four_title'=>'string','project_four_meta'=>'string','project_four_image_url'=>'image','project_five_title'=>'string','project_five_meta'=>'string','project_five_image_url'=>'image','project_six_title'=>'string','project_six_meta'=>'string','project_six_image_url'=>'image'],
            'portfolio_hover_video' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','project_one_title'=>'string','project_one_meta'=>'string','project_one_image_url'=>'image','project_two_title'=>'string','project_two_meta'=>'string','project_two_image_url'=>'image','project_three_title'=>'string','project_three_meta'=>'string','project_three_image_url'=>'image','project_four_title'=>'string','project_four_meta'=>'string','project_four_image_url'=>'image','project_five_title'=>'string','project_five_meta'=>'string','project_five_image_url'=>'image','project_six_title'=>'string','project_six_meta'=>'string','project_six_image_url'=>'image','project_one_video_url'=>'string','project_two_video_url'=>'string','project_three_video_url'=>'string','project_four_video_url'=>'string','project_five_video_url'=>'string','project_six_video_url'=>'string'],
            'portfolio_case_study' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','project_title'=>'string','project_meta'=>'string','image_url'=>'image','challenge_label'=>'string','challenge_text'=>'string','approach_label'=>'string','approach_text'=>'string','outcome_label'=>'string','outcome_text'=>'string','metric_value'=>'string','metric_label'=>'string','primary_label'=>'string','primary_url'=>'url','footnote'=>'string'],
            'portfolio_before_after' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','project_title'=>'string','project_meta'=>'string','before_label'=>'string','after_label'=>'string','before_image_url'=>'image','after_image_url'=>'image','outcome_label'=>'string','outcome_text'=>'string','primary_label'=>'string','primary_url'=>'url'],
            'portfolio_filterable' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','project_one_title'=>'string','project_one_meta'=>'string','project_one_image_url'=>'image','project_one_category'=>'string','project_two_title'=>'string','project_two_meta'=>'string','project_two_image_url'=>'image','project_two_category'=>'string','project_three_title'=>'string','project_three_meta'=>'string','project_three_image_url'=>'image','project_three_category'=>'string','project_four_title'=>'string','project_four_meta'=>'string','project_four_image_url'=>'image','project_four_category'=>'string','project_five_title'=>'string','project_five_meta'=>'string','project_five_image_url'=>'image','project_five_category'=>'string','project_six_title'=>'string','project_six_meta'=>'string','project_six_image_url'=>'image','project_six_category'=>'string'],
            'portfolio_animated' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','project_one_title'=>'string','project_one_meta'=>'string','project_one_image_url'=>'image','project_two_title'=>'string','project_two_meta'=>'string','project_two_image_url'=>'image','project_three_title'=>'string','project_three_meta'=>'string','project_three_image_url'=>'image','project_four_title'=>'string','project_four_meta'=>'string','project_four_image_url'=>'image'],
            'portfolio_project_timeline' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','project_title'=>'string','project_meta'=>'string','primary_label'=>'string','primary_url'=>'url','step_one_number'=>'string','step_one_title'=>'string','step_one_text'=>'string','step_one_image_url'=>'image','step_two_number'=>'string','step_two_title'=>'string','step_two_text'=>'string','step_two_image_url'=>'image','step_three_number'=>'string','step_three_title'=>'string','step_three_text'=>'string','step_three_image_url'=>'image','step_four_number'=>'string','step_four_title'=>'string','step_four_text'=>'string','step_four_image_url'=>'image'],
            'services_feature_comparison' => ['eyebrow'=>'string', 'heading'=>'string', 'text'=>'string', 'option_one_name'=>'string', 'option_one_kicker'=>'string', 'option_one_text'=>'string', 'option_two_name'=>'string', 'option_two_kicker'=>'string', 'option_two_text'=>'string', 'option_two_badge'=>'string', 'option_three_name'=>'string', 'option_three_kicker'=>'string', 'option_three_text'=>'string', 'feature_one'=>'string', 'feature_two'=>'string', 'feature_three'=>'string', 'feature_four'=>'string', 'feature_five'=>'string', 'feature_six'=>'string', 'feature_seven'=>'string', 'feature_eight'=>'string', 'option_one_one'=>'string', 'option_one_two'=>'string', 'option_one_three'=>'string', 'option_one_four'=>'string', 'option_one_five'=>'string', 'option_one_six'=>'string', 'option_one_seven'=>'string', 'option_one_eight'=>'string', 'option_two_one'=>'string', 'option_two_two'=>'string', 'option_two_three'=>'string', 'option_two_four'=>'string', 'option_two_five'=>'string', 'option_two_six'=>'string', 'option_two_seven'=>'string', 'option_two_eight'=>'string', 'option_three_one'=>'string', 'option_three_two'=>'string', 'option_three_three'=>'string', 'option_three_four'=>'string', 'option_three_five'=>'string', 'option_three_six'=>'string', 'option_three_seven'=>'string', 'option_three_eight'=>'string', 'primary_label'=>'string', 'primary_url'=>'url', 'footnote'=>'string'],
            'services_cards' => ['tagline'=>'string','heading'=>'string','description'=>'string','cards'=>['count'=>3,'items'=>$card]],
            'hero_centered_cta' => ['tagline'=>'string','heading'=>'string','subheading'=>'string'],
            'cta_glass_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url'],
            'cta_gradient_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url'],
            'cta_newsletter_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','input_placeholder'=>'string','privacy_note'=>'string'],
            'cta_book_demo_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url'],
            'cta_calendly_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','booking_url'=>'url','availability_note'=>'string','duration_label'=>'string'],
            'cta_free_trial_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','trial_note'=>'string','benefit_one'=>'string','benefit_two'=>'string','benefit_three'=>'string'],
            'cta_countdown_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','countdown_deadline'=>'string','countdown_days'=>'string','countdown_hours'=>'string','countdown_minutes'=>'string','countdown_seconds'=>'string','deadline_note'=>'string'],
            'cta_limited_offer_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','offer_badge'=>'string','offer_detail'=>'string','terms_note'=>'string'],
            'contact_split_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','email'=>'string','phone'=>'string','address'=>'string','primary_label'=>'string','primary_url'=>'url','fields'=>['count'=>3,'items'=>['id'=>'string','name'=>'string','type'=>'string','label'=>'string','placeholder'=>'string','required'=>'bool','options'=>'array']]],
            'contact_map_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','email'=>'string','phone'=>'string','address'=>'string','primary_label'=>'string','primary_url'=>'url','map_label'=>'string','directions_label'=>'string','directions_url'=>'url'],
            'contact_appointment_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','email'=>'string','phone'=>'string','address'=>'string','primary_label'=>'string','primary_url'=>'url','booking_url'=>'url','appointment_note'=>'string','duration_label'=>'string'],
            'contact_support_center_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','email'=>'string','phone'=>'string','address'=>'string','primary_label'=>'string','primary_url'=>'url','support_one_title'=>'string','support_one_text'=>'string','support_two_title'=>'string','support_two_text'=>'string','support_three_title'=>'string','support_three_text'=>'string'],
            'contact_faq_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','email'=>'string','phone'=>'string','address'=>'string','primary_label'=>'string','primary_url'=>'url','faq_one_question'=>'string','faq_one_answer'=>'string','faq_two_question'=>'string','faq_two_answer'=>'string','faq_three_question'=>'string','faq_three_answer'=>'string','fields'=>['count'=>3,'items'=>['id'=>'string','name'=>'string','type'=>'string','label'=>'string','placeholder'=>'string','required'=>'bool','options'=>'array']]],
            'contact_multistep_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','email'=>'string','phone'=>'string','address'=>'string','primary_label'=>'string','primary_url'=>'url','step_one_title'=>'string','step_one_text'=>'string','step_two_title'=>'string','step_two_text'=>'string','step_three_title'=>'string','step_three_text'=>'string','submit_label'=>'string','fields'=>['count'=>3,'items'=>['id'=>'string','name'=>'string','type'=>'string','label'=>'string','placeholder'=>'string','required'=>'bool','options'=>'array']]],
            'contact_live_chat_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','email'=>'string','phone'=>'string','address'=>'string','primary_label'=>'string','primary_url'=>'url','chat_url'=>'url','chat_note'=>'string','secondary_label'=>'string','secondary_url'=>'url'],
            'blog_magazine_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','feature_title'=>'string','feature_excerpt'=>'string','feature_image_url'=>'image','story_one_title'=>'string','story_one_meta'=>'string','story_two_title'=>'string','story_two_meta'=>'string','story_three_title'=>'string','story_three_meta'=>'string'],
            'blog_featured_article_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','feature_category'=>'string','feature_title'=>'string','feature_excerpt'=>'string','feature_image_url'=>'image','author_line'=>'string'],
            'blog_editors_pick_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','pick_title'=>'string','pick_excerpt'=>'string','pick_image_url'=>'image','item_one'=>'string','item_two'=>'string','item_three'=>'string'],
            'blog_sidebar_news_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','lead_title'=>'string','lead_excerpt'=>'string','lead_image_url'=>'image','news_one_title'=>'string','news_one_meta'=>'string','news_two_title'=>'string','news_two_meta'=>'string','topic_one'=>'string','topic_two'=>'string','topic_three'=>'string'],
            'blog_newsletter_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','newsletter_note'=>'string','topic_one'=>'string','topic_two'=>'string','topic_three'=>'string'],
            'blog_trending_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','item_one_title'=>'string','item_one_meta'=>'string','item_two_title'=>'string','item_two_meta'=>'string','item_three_title'=>'string','item_three_meta'=>'string','item_four_title'=>'string','item_four_meta'=>'string'],
            'blog_categories_grid_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','category_one'=>'string','category_one_text'=>'string','category_two'=>'string','category_two_text'=>'string','category_three'=>'string','category_three_text'=>'string','category_four'=>'string','category_four_text'=>'string'],
            'blog_author_profile_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','author_name'=>'string','author_role'=>'string','author_bio'=>'string','author_image_url'=>'image','specialty_one'=>'string','specialty_two'=>'string','specialty_three'=>'string'],
            'process_timeline' => ['category'=>'string','heading'=>'string','text'=>'string','steps'=>['count'=>4,'items'=>['number'=>'string','title'=>'string','text'=>'string']]],
            'stats_modern' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','metrics'=>['count'=>4,'items'=>['value'=>'string','label'=>'string','description'=>'string']]],
            'stats_animated_counters_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','metrics'=>['count'=>4,'items'=>['value'=>'string','label'=>'string','description'=>'string']]],
            'stats_revenue_dashboard_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','period_label'=>'string','metrics'=>['count'=>4,'items'=>['value'=>'string','label'=>'string','description'=>'string']]],
            'stats_growth_charts_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','chart_label'=>'string','series'=>['count'=>6,'items'=>['label'=>'string','value'=>'number']],'metrics'=>['count'=>3,'items'=>['value'=>'string','label'=>'string','description'=>'string']]],
            'stats_achievements_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','achievements'=>['count'=>4,'items'=>['year'=>'string','badge'=>'string','title'=>'string','text'=>'string']]],
            'stats_global_presence_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','locations'=>['count'=>4,'items'=>['region'=>'string','value'=>'string','label'=>'string','description'=>'string']]],
            'stats_timeline_metrics_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','periods'=>['count'=>4,'items'=>['period'=>'string','value'=>'string','label'=>'string','description'=>'string']]],
            'team_modern' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','members'=>['count'=>4,'items'=>['name'=>'string','role'=>'string','bio'=>'string','image_url'=>'string']]],
            'team_cards_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','members'=>['count'=>4,'items'=>['name'=>'string','role'=>'string','bio'=>'string','image_url'=>'string','level'=>'string']]],
            'team_timeline_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','timeline_label'=>'string','members'=>['count'=>4,'items'=>['name'=>'string','role'=>'string','bio'=>'string','image_url'=>'string','level'=>'string']]],
            'team_org_chart_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','chart_label'=>'string','members'=>['count'=>4,'items'=>['name'=>'string','role'=>'string','bio'=>'string','image_url'=>'string','level'=>'string']]],
            'team_leadership_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','lead_label'=>'string','members'=>['count'=>4,'items'=>['name'=>'string','role'=>'string','bio'=>'string','image_url'=>'string','level'=>'string']]],
            'team_culture_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','culture_label'=>'string','values'=>['count'=>4,'items'=>['title'=>'string','text'=>'string']]],
            'team_open_positions_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','positions_label'=>'string','primary_label'=>'string','primary_url'=>'url','positions'=>['count'=>4,'items'=>['title'=>'string','meta'=>'string','location'=>'string','summary'=>'string']]],
            'testimonials_carousel' => ['tagline'=>'string','heading'=>'string','text'=>'string','testimonials'=>['count'=>3,'items'=>['avatar'=>'string','name'=>'string','company'=>'string','quote'=>'string','rating'=>'rating']]],
            'testimonials_video_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','video_url'=>'url','video_label'=>'string','testimonials'=>['count'=>4,'items'=>['avatar'=>'image','name'=>'string','company'=>'string','quote'=>'string']]],
            'testimonials_scrolling_marquee' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','testimonials'=>['count'=>4,'items'=>['avatar'=>'image','name'=>'string','company'=>'string','quote'=>'string']]],
            'testimonials_wall_of_love' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','testimonials'=>['count'=>4,'items'=>['avatar'=>'image','name'=>'string','company'=>'string','quote'=>'string']]],
            'testimonials_card_stack' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','testimonials'=>['count'=>4,'items'=>['avatar'=>'image','name'=>'string','company'=>'string','quote'=>'string']]],
            'testimonials_trust_dashboard' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','testimonials'=>['count'=>4,'items'=>['avatar'=>'image','name'=>'string','company'=>'string','quote'=>'string']]],
            'testimonials_review_grid' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','testimonials'=>['count'=>4,'items'=>['avatar'=>'image','name'=>'string','company'=>'string','quote'=>'string']]],
            'testimonials_review_carousel_pro' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','testimonials'=>['count'=>4,'items'=>['avatar'=>'image','name'=>'string','company'=>'string','quote'=>'string']]],
            'hero_parallax' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','overlayOpacity'=>'int','parallaxSpeed'=>'int','contentAlign'=>'string','height'=>'string','scroll_label'=>'string'],
            'hero_ken_burns_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','interval'=>'int','parallaxStrength'=>'int','pointerStrength'=>'int'],
            'hero_crossfade_gallery_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','interval'=>'int','parallaxStrength'=>'int','pointerStrength'=>'int'],
            'hero_cinematic_slider_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','interval'=>'int','parallaxStrength'=>'int','pointerStrength'=>'int'],
            'hero_split_slider_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','interval'=>'int','parallaxStrength'=>'int','pointerStrength'=>'int'],
            'hero_vertical_story_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','interval'=>'int','parallaxStrength'=>'int','pointerStrength'=>'int'],
            'hero_parallax_layers_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','interval'=>'int','parallaxStrength'=>'int','pointerStrength'=>'int'],
            'hero_mouse_parallax_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','interval'=>'int','parallaxStrength'=>'int','pointerStrength'=>'int'],
            'hero_reveal_parallax_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','poster_image_url'=>'image','video_url'=>'string','overlayOpacity'=>'int','motionStrength'=>'int'],
            'hero_zoom_scroll_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','poster_image_url'=>'image','video_url'=>'string','overlayOpacity'=>'int','motionStrength'=>'int'],
            'hero_pinned_story_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','poster_image_url'=>'image','video_url'=>'string','overlayOpacity'=>'int','motionStrength'=>'int'],
            'hero_video_cinematic_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','poster_image_url'=>'image','video_url'=>'string','overlayOpacity'=>'int','motionStrength'=>'int'],
            'hero_video_split_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','poster_image_url'=>'image','video_url'=>'string','overlayOpacity'=>'int','motionStrength'=>'int'],
            'hero_aurora_motion_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','poster_image_url'=>'image','video_url'=>'string','overlayOpacity'=>'int','motionStrength'=>'int'],
            'hero_mesh_gradient_motion_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','poster_image_url'=>'image','video_url'=>'string','overlayOpacity'=>'int','motionStrength'=>'int'],
            'hero_spotlight_cursor_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','pointerStrength'=>'int','interval'=>'int','rotating_words'=>'array'],
            'hero_floating_cards_motion_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','pointerStrength'=>'int','interval'=>'int','rotating_words'=>'array'],
            'hero_3d_tilt_product_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','pointerStrength'=>'int','interval'=>'int','rotating_words'=>'array'],
            'hero_infinite_marquee_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','pointerStrength'=>'int','interval'=>'int','rotating_words'=>'array'],
            'hero_rotating_words_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','pointerStrength'=>'int','interval'=>'int','rotating_words'=>'array'],
            'hero_typewriter_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','pointerStrength'=>'int','interval'=>'int','rotating_words'=>'array'],
            'hero_curtain_reveal_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','pointerStrength'=>'int','interval'=>'int','rotating_words'=>'array'],
            'hero_image_mask_reveal_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','motionStrength'=>'int','splitPosition'=>'int','particleCount'=>'int'],
            'hero_stacked_cards_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','motionStrength'=>'int','splitPosition'=>'int','particleCount'=>'int'],
            'hero_perspective_carousel_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','motionStrength'=>'int','splitPosition'=>'int','particleCount'=>'int'],
            'hero_before_after_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','motionStrength'=>'int','splitPosition'=>'int','particleCount'=>'int'],
            'hero_scroll_morph_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','motionStrength'=>'int','splitPosition'=>'int','particleCount'=>'int'],
            'hero_glass_orb_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','motionStrength'=>'int','splitPosition'=>'int','particleCount'=>'int'],
            'hero_particle_constellation_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image','overlayOpacity'=>'int','motionStrength'=>'int','splitPosition'=>'int','particleCount'=>'int'],
            'hero_grid_pulse_tech_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image'],
            'hero_light_trails_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image'],
            'hero_device_showcase_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image'],
            'hero_app_screens_carousel_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image'],
            'hero_editorial_image_sequence_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image'],
            'hero_interactive_bento_premium' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_url_2'=>'image','image_url_3'=>'image'],
            'hero_background_image' => ['category'=>'string','tagline'=>'string','heading'=>'string','text'=>'string','button_label'=>'string','button_url'=>'url','image_url'=>'image','overlayOpacity'=>'int','textAlign'=>'string','height'=>'string'],
            'hero_slider_fade' => ['category'=>'string','autoplay'=>'bool','interval'=>'int','pause_on_hover'=>'bool','show_dots'=>'bool','show_arrows'=>'bool','slides'=>['count'=>3,'items'=>['image_url'=>'image','eyebrow'=>'string','heading'=>'string','description'=>'string','button_1_text'=>'string','button_1_url'=>'url','button_2_text'=>'string','button_2_url'=>'url','button_3_text'=>'string','button_3_url'=>'url','button_4_text'=>'string','button_4_url'=>'url']]],
            'pricing_cards' => ['category'=>'string','tagline'=>'string','heading'=>'string','text'=>'string','plans'=>['count'=>3,'items'=>['badge'=>'string','featured'=>'bool','title'=>'string','price'=>'string','period'=>'string','description'=>'string','button_label'=>'string','button_url'=>'url','features'=>['count'=>5,'items'=>['text'=>'string']]]]],
            'pricing_comparison_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string'],
            'pricing_toggle_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string'],
            'pricing_enterprise_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string'],
            'pricing_calculator_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string'],
            'pricing_credit_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string'],
            'pricing_agency_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string'],
            'pricing_feature_matrix_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string'],
            'hero_editorial_overlay' => ['category'=>'string','tagline'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','overlayOpacity'=>'int','height'=>'string'],
            'hero_split_image' => ['tagline'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','trust_line'=>'string','image_badge'=>'string','image_url'=>'image'],
            'hero_split_editorial' => ['eyebrow'=>'string','editorial_index'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','proof_value'=>'string','proof_label'=>'string','image_caption'=>'string','image_url'=>'image'],
            'hero_floating_glass' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','glass_title'=>'string','glass_text'=>'string','metric_value'=>'string','metric_label'=>'string','badge_one'=>'string','badge_two'=>'string','image_url'=>'image'],
            'hero_saas_dashboard' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','dashboard_title'=>'string','dashboard_subtitle'=>'string','metric_one_value'=>'string','metric_one_label'=>'string','metric_two_value'=>'string','metric_two_label'=>'string','metric_three_value'=>'string','metric_three_label'=>'string','chart_label'=>'string','logo_one'=>'string','logo_two'=>'string','logo_three'=>'string','logo_four'=>'string'],
            'hero_luxury_fullscreen' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','location_label'=>'string','edition_label'=>'string','image_url'=>'image'],
            'hero_video_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','media_badge'=>'string','scroll_label'=>'string','video_url'=>'string','poster_image_url'=>'image'],
            'hero_ai_conversation' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','assistant_label'=>'string','assistant_status'=>'string','user_message'=>'string','assistant_message'=>'string','prompt_placeholder'=>'string','chip_one'=>'string','chip_two'=>'string','chip_three'=>'string'],
            'hero_agency_showcase' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','before_label'=>'string','before_caption'=>'string','after_label'=>'string','after_caption'=>'string','metric_one_value'=>'string','metric_one_label'=>'string','metric_two_value'=>'string','metric_two_label'=>'string','metric_three_value'=>'string','metric_three_label'=>'string','logo_one'=>'string','logo_two'=>'string','logo_three'=>'string','logo_four'=>'string','before_image_url'=>'image','after_image_url'=>'image'],
            'hero_bento_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_label'=>'string','metric_value'=>'string','metric_label'=>'string','proof_title'=>'string','proof_text'=>'string','card_one_label'=>'string','card_two_label'=>'string','card_three_label'=>'string'],
            'image_cta_banner' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','overlayOpacity'=>'int'],
            'hero_floating_cards' => ['tagline'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','image_badge'=>'string','card_one_value'=>'string','card_one_label'=>'string','card_two_value'=>'string','card_two_label'=>'string','card_three_value'=>'string','card_three_label'=>'string'],
            'hero_video_style' => ['tagline'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','video_label'=>'string','video_url'=>'url','play_label'=>'string','image_badge'=>'string','image_url'=>'image'],
            'hero_video_background' => ['tagline'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','video_url'=>'fixed:/storage/cms-videos/hero-placeholder.mp4','poster_image_url'=>'image','video_badge'=>'string','scroll_label'=>'string'],
            'contact_form_modern' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','email'=>'string','phone'=>'string','address'=>'string','submit_label'=>'string','fields'=>['count'=>3,'items'=>['id'=>'string','name'=>'string','type'=>'string','label'=>'string','placeholder'=>'string','required'=>'bool','options'=>'array']]],
            'faq_accordion' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','faqs'=>['count'=>4,'items'=>['question'=>'string','answer'=>'string']]],
            'faq_accordion_pro' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','faqs'=>['count'=>6,'items'=>['question'=>'string','answer'=>'string']]],
            'faq_search_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','search_placeholder'=>'string','faqs'=>['count'=>8,'items'=>['question'=>'string','answer'=>'string']]],
            'faq_categories_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','categories'=>['count'=>3,'items'=>['title'=>'string','description'=>'string','faqs'=>['count'=>2,'items'=>['question'=>'string','answer'=>'string']]]]],
            'faq_support_portal_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','topics'=>['count'=>4,'items'=>['title'=>'string','description'=>'string','count_label'=>'string']],'faqs'=>['count'=>4,'items'=>['question'=>'string','answer'=>'string']]],
            'faq_documentation_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','topics'=>['count'=>4,'items'=>['title'=>'string','text'=>'string']],'featured_title'=>'string','featured_text'=>'string','steps'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']]],
            'lead_magnet_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','resource_label'=>'string','resource_title'=>'string','resource_text'=>'string','primary_label'=>'string','primary_url'=>'url','benefits'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']]],
            'lead_free_audit_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','offer_label'=>'string','primary_label'=>'string','primary_url'=>'url','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'lead_website_audit_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','audit_items'=>['count'=>4,'items'=>['title'=>'string','text'=>'string']],'report_label'=>'string','report_title'=>'string','report_text'=>'string'],
            'lead_quote_form_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'form_note'=>'string'],
            'lead_roi_calculator_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','input_one_label'=>'string','input_two_label'=>'string','input_three_label'=>'string','result_label'=>'string','note'=>'string'],
            'lead_cost_calculator_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','quantity_label'=>'string','rate_label'=>'string','result_label'=>'string','note'=>'string'],
            'lead_consultation_booking_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'sales_comparison_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'sales_feature_matrix_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'sales_competitor_comparison_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'sales_roi_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'sales_guarantee_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'sales_trust_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'sales_integrations_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'agency_dashboard_preview_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'agency_client_portal_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'agency_white_label_showcase_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'agency_website_management_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'agency_maintenance_plans_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'agency_support_plans_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'agency_workflow_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'agency_project_pipeline_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'agency_client_reviews_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'agency_website_reports_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'ai_prompt_showcase_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'ai_workflow_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'ai_assistant_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'ai_timeline_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'ai_builder_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'ai_automation_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'ai_credits_dashboard_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'ai_generation_process_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'ai_statistics_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'ai_prompt_examples_premium' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','items'=>['count'=>3,'items'=>['title'=>'string','text'=>'string']],'note'=>'string'],
            'contact_details' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','email'=>'string','phone'=>'string','address'=>'string','hours'=>'string'],
            'location_map' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','location_name'=>'string','address'=>'string','service_area'=>'string','directions_label'=>'string'],
            'case_studies_grid' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','studies'=>['count'=>3,'items'=>['category'=>'string','title'=>'string','summary'=>'string','result'=>'string','image_url'=>'image','link_label'=>'string']]],
            'jobs_list' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','jobs'=>['count'=>4,'items'=>['title'=>'string','type'=>'string','location'=>'string','description'=>'string','button_label'=>'string']]],
            'events_grid' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','events'=>['count'=>3,'items'=>['month'=>'string','day'=>'string','title'=>'string','date'=>'string','location'=>'string','description'=>'string','button_label'=>'string']]],
        ];
    }
}
