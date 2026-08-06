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
            'services_feature_comparison' => ['eyebrow'=>'string', 'heading'=>'string', 'text'=>'string', 'option_one_name'=>'string', 'option_one_kicker'=>'string', 'option_one_text'=>'string', 'option_two_name'=>'string', 'option_two_kicker'=>'string', 'option_two_text'=>'string', 'option_two_badge'=>'string', 'option_three_name'=>'string', 'option_three_kicker'=>'string', 'option_three_text'=>'string', 'feature_one'=>'string', 'feature_two'=>'string', 'feature_three'=>'string', 'feature_four'=>'string', 'feature_five'=>'string', 'feature_six'=>'string', 'feature_seven'=>'string', 'feature_eight'=>'string', 'option_one_one'=>'string', 'option_one_two'=>'string', 'option_one_three'=>'string', 'option_one_four'=>'string', 'option_one_five'=>'string', 'option_one_six'=>'string', 'option_one_seven'=>'string', 'option_one_eight'=>'string', 'option_two_one'=>'string', 'option_two_two'=>'string', 'option_two_three'=>'string', 'option_two_four'=>'string', 'option_two_five'=>'string', 'option_two_six'=>'string', 'option_two_seven'=>'string', 'option_two_eight'=>'string', 'option_three_one'=>'string', 'option_three_two'=>'string', 'option_three_three'=>'string', 'option_three_four'=>'string', 'option_three_five'=>'string', 'option_three_six'=>'string', 'option_three_seven'=>'string', 'option_three_eight'=>'string', 'primary_label'=>'string', 'primary_url'=>'url', 'footnote'=>'string'],
            'services_cards' => ['tagline'=>'string','heading'=>'string','description'=>'string','cards'=>['count'=>3,'items'=>$card]],
            'hero_centered_cta' => ['tagline'=>'string','heading'=>'string','subheading'=>'string'],
            'process_timeline' => ['category'=>'string','heading'=>'string','text'=>'string','steps'=>['count'=>4,'items'=>['number'=>'string','title'=>'string','text'=>'string']]],
            'stats_modern' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','metrics'=>['count'=>4,'items'=>['value'=>'string','label'=>'string','description'=>'string']]],
            'team_modern' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','members'=>['count'=>4,'items'=>['name'=>'string','role'=>'string','bio'=>'string','image_url'=>'string']]],
            'testimonials_carousel' => ['tagline'=>'string','heading'=>'string','text'=>'string','testimonials'=>['count'=>3,'items'=>['avatar'=>'string','name'=>'string','company'=>'string','quote'=>'string','rating'=>'rating']]],
            'hero_parallax' => ['category'=>'string','eyebrow'=>'string','heading'=>'string','text'=>'string','primary_label'=>'string','primary_url'=>'url','secondary_label'=>'string','secondary_url'=>'url','image_url'=>'image','overlayOpacity'=>'int','parallaxSpeed'=>'int','contentAlign'=>'string','height'=>'string','scroll_label'=>'string'],
            'hero_background_image' => ['category'=>'string','tagline'=>'string','heading'=>'string','text'=>'string','button_label'=>'string','button_url'=>'url','image_url'=>'image','overlayOpacity'=>'int','textAlign'=>'string','height'=>'string'],
            'hero_slider_fade' => ['category'=>'string','autoplay'=>'bool','interval'=>'int','pause_on_hover'=>'bool','show_dots'=>'bool','show_arrows'=>'bool','slides'=>['count'=>3,'items'=>['image_url'=>'image','eyebrow'=>'string','heading'=>'string','description'=>'string','button_1_text'=>'string','button_1_url'=>'url','button_2_text'=>'string','button_2_url'=>'url','button_3_text'=>'string','button_3_url'=>'url','button_4_text'=>'string','button_4_url'=>'url']]],
            'pricing_cards' => ['category'=>'string','tagline'=>'string','heading'=>'string','text'=>'string','plans'=>['count'=>3,'items'=>['badge'=>'string','featured'=>'bool','title'=>'string','price'=>'string','period'=>'string','description'=>'string','button_label'=>'string','button_url'=>'url','features'=>['count'=>5,'items'=>['text'=>'string']]]]],
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
            'contact_details' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','email'=>'string','phone'=>'string','address'=>'string','hours'=>'string'],
            'location_map' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','location_name'=>'string','address'=>'string','service_area'=>'string','directions_label'=>'string'],
            'case_studies_grid' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','studies'=>['count'=>3,'items'=>['category'=>'string','title'=>'string','summary'=>'string','result'=>'string','image_url'=>'image','link_label'=>'string']]],
            'jobs_list' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','jobs'=>['count'=>4,'items'=>['title'=>'string','type'=>'string','location'=>'string','description'=>'string','button_label'=>'string']]],
            'events_grid' => ['eyebrow'=>'string','heading'=>'string','text'=>'string','events'=>['count'=>3,'items'=>['month'=>'string','day'=>'string','title'=>'string','date'=>'string','location'=>'string','description'=>'string','button_label'=>'string']]],
        ];
    }
}
