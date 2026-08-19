<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class LunaCategoryPageService
{
    public const CATEGORIES = ['hero','about','services','features','portfolio','process','stats','testimonials','team','pricing','faq','contact','cta'];

    public function plan(string $prompt): array
    {
        $system = <<<'TXT'
You are Luna, the Cosmic CMS page art director. Plan a page using CATEGORY SCHEMAS, not existing Spark templates.
Return JSON only: {"sections":["luna:hero","luna:services"...]}
Allowed categories: hero, about, services, features, portfolio, process, stats, testimonials, team, pricing, faq, contact, cta.
Choose 5-9 sections. Use at most one hero and put it first when appropriate. Choose only categories relevant to the page brief. End with contact or CTA when appropriate. Vary storytelling order between generations when the prompt includes a design seed or previous-generation context.
TXT;
        $response = OpenAI::chat()->create([
            'model' => config('openai.planner_model', env('OPENAI_MODEL', 'gpt-5-mini')),
            'messages' => [
                ['role'=>'system','content'=>$system],
                ['role'=>'user','content'=>$prompt],
            ],
        ]);
        $data = $this->decode((string)($response->choices[0]->message->content ?? ''));
        $sections=[];
        foreach (($data['sections'] ?? []) as $section) {
            if (!is_string($section)) continue;
            $category = str_starts_with($section,'luna:') ? substr($section,5) : $section;
            if (in_array($category,self::CATEGORIES,true) && !in_array('luna:'.$category,$sections,true)) $sections[]='luna:'.$category;
        }
        if (!$sections) throw new RuntimeException('Luna did not return supported page categories.');
        return $sections;
    }

    public function generate(string $prompt, array $sections): array
    {
        $categories = array_values(array_filter(array_map(fn($s)=>str_starts_with((string)$s,'luna:')?substr((string)$s,5):(string)$s,$sections), fn($s)=>in_array($s,self::CATEGORIES,true)));
        $categoryList = implode(', ', $categories);
        $system = <<<'TXT'
You are Luna, the Cosmic CMS UI designer. Create NEW custom section designs from category schemas. Do NOT choose or name any existing Cosmic Spark.
Return valid JSON only with shape {"blocks":[...]} and exactly one block per requested category, same order.
Every block must use type="luna_custom_section" and contain:
category: requested category
layout: one of split, editorial, bento, stacked, spotlight, mosaic, centered, rail, timeline, showcase
alignment: left, center, right
media_position: left, right, background, top, none
density: airy, balanced, compact
accent_shape: none, glow, orb, line, grid, frame
section_mood: auto, dark, surface, light, primary, accent, image_overlay
heading, eyebrow, text
primary_label, primary_url, secondary_label, secondary_url
image_url: ""
items: dynamic array of objects. Never limit item count. Use as many items as the business/user request requires. Each item may contain {title,text,label,value,image_url,meta}.

CATEGORY CONTENT CONTRACTS:
hero = strong opening, optional proof items, 1-2 CTAs; prefer image/media unless brief calls for text-first.
about = story/mission/values, truthful only.
services = service offerings in items; do not force cards.
features = benefits/capabilities in items.
portfolio = project/work presentation; do not invent client names or results.
process = ordered steps in items.
stats = only verified numbers from prompt; otherwise use non-numeric proof labels.
testimonials = never invent named customer quotes; use clearly editable neutral starter proof if none supplied.
team = only supplied people/roles; otherwise generic team positioning without fake profiles.
pricing = only supplied prices; otherwise package labels without fabricated prices.
faq = question/answer pairs in items.
contact = concise contact invitation; do not invent phone/address.
cta = focused closing conversion section.

UI DESIGN RULES:
- You are designing the UI composition, not selecting templates. Make each section feel art-directed for this business.
- Vary layouts across the page; do not repeat the same layout more than twice.
- Avoid long runs of generic equal cards. Use asymmetry, editorial spacing, media placement, visual hierarchy, and mixed density where suitable.
- Use the website brand context in the prompt. Color application is tokenized by Cosmic; never output raw hex colors.
- All text must be production-ready and factual. URLs default to # when unknown.
TXT;
        $user = "WEBSITE REQUEST\n\n{$prompt}\n\nREQUESTED CATEGORIES\n{$categoryList}\n\nCreate the custom UI schema now.";
        $response = OpenAI::chat()->create([
            'model' => config('openai.content_model', env('OPENAI_MODEL', 'gpt-5-mini')),
            'messages' => [
                ['role'=>'system','content'=>$system],
                ['role'=>'user','content'=>$user],
            ],
        ]);
        $data=$this->decode((string)($response->choices[0]->message->content ?? ''));
        $blocks=[];
        foreach (($data['blocks'] ?? []) as $i=>$block) {
            if (!is_array($block) || !isset($categories[$i])) continue;
            $block['type']='luna_custom_section';
            $block['category']=$categories[$i];
            $block['theme']=$block['theme'] ?? 'auto';
            $block['image_url']='';
            $block['layout']=in_array(($block['layout']??''),['split','editorial','bento','stacked','spotlight','mosaic','centered','rail','timeline','showcase'],true)?$block['layout']:'editorial';
            $block['alignment']=in_array(($block['alignment']??''),['left','center','right'],true)?$block['alignment']:'left';
            $block['media_position']=in_array(($block['media_position']??''),['left','right','background','top','none'],true)?$block['media_position']:'none';
            $block['density']=in_array(($block['density']??''),['airy','balanced','compact'],true)?$block['density']:'balanced';
            $block['accent_shape']=in_array(($block['accent_shape']??''),['none','glow','orb','line','grid','frame'],true)?$block['accent_shape']:'none';
            $block['items']=array_slice(is_array($block['items']??null)?$block['items']:[],0,6);
            $blocks[]=$block;
        }
        if (count($blocks)!==count($categories)) throw new RuntimeException('Luna custom UI response was incomplete.');
        return $blocks;
    }

    private function decode(string $content): array
    {
        $content=preg_replace('/^```json\s*/i','',trim($content)) ?? $content;
        $content=preg_replace('/^```\s*/i','',$content) ?? $content;
        $content=preg_replace('/```\s*$/i','',$content) ?? $content;
        $data=json_decode(trim($content),true);
        if (!is_array($data)) throw new RuntimeException('Luna returned invalid JSON.');
        return $data;
    }
}
