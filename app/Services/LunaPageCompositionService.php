<?php

namespace App\Services;

use Illuminate\Support\Str;

final class LunaPageCompositionService
{
    private array $rules;

    public function __construct(private readonly TemplateQualityAuditor $qualityAuditor)
    {
        $path=resource_path('luna/page_composition.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->rules=is_array($decoded)?$decoded:[];
    }

    public function context(string $prompt): array
    {
        $q=Str::lower(trim($prompt));
        $pageIntent=$this->matchGroup($q,$this->rules['page_intents']??[]) ?: 'home';
        $industry=$this->matchGroup($q,$this->rules['industries']??[]) ?: $this->inferBroadIndustry($q);
        $page=$this->rules['page_intents'][$pageIntent]??[];
        $industryRules=$this->rules['industries'][$industry]??[];

        return [
            'page_intent'=>$pageIntent,
            'industry'=>$industry,
            'required_roles'=>$page['required_roles']??['hero','value','proof','conversion'],
            'optional_roles'=>$page['optional_roles']??[],
            'preferred_count'=>$page['preferred_count']??[5,7],
            'industry_priorities'=>$industryRules['priorities']??[],
            'industry_content'=>$industryRules['content']??[],
            'avoid'=>$industryRules['avoid']??[],
            'rhythm_rules'=>$this->rules['rhythm']['rules']??[],
        ];
    }

    public function audit(array $sections,array $context): array
    {
        $roles=array_map(fn($section)=>$this->sectionRole((string)$section),$sections);
        $issues=[];
        $heroCount=count(array_filter($roles,fn($r)=>$r==='hero'));
        if($heroCount!==1)$issues[]="Composition should contain exactly one hero; found {$heroCount}.";

        foreach($context['required_roles']??[] as $required){
            if(!$this->roleSatisfied($required,$roles)){
                $issues[]="Missing page-purpose role: {$required}.";
            }
        }

        $denseRun=0;
        foreach($sections as $section){
            if($this->isDense((string)$section)){
                $denseRun++;
                if($denseRun>2){
                    $issues[]='More than two dense card/grid sections are consecutive.';
                    break;
                }
            }else{$denseRun=0;}
        }

        $imageProfile=$this->qualityAuditor->imageProfile($sections);
        $visualIntent=in_array((string)($context['industry']??''),['restaurant','hospitality'],true)
            || (string)($context['page_intent']??'')==='work';
        $ratioLimit=$visualIntent?0.60:0.55;
        $runLimit=1;
        if(($imageProfile['ratio']??0)>$ratioLimit){
            $issues[]=sprintf('Image-heavy section ratio is %.0f%%; mix in a compatible story, information, process, or proof Spark.',($imageProfile['ratio']??0)*100);
        }
        if(($imageProfile['max_consecutive']??0)>$runLimit){
            $issues[]='Too many image-heavy sections are consecutive; break the run with a compatible non-image Spark.';
        }

        $last=$roles ? $roles[array_key_last($roles)] : null;
        if(!in_array($last,['conversion','contact','pricing'],true)){
            $issues[]='The page does not end with a clear conversion/contact role.';
        }

        return [
            'pass'=>$issues===[],
            'roles'=>$roles,
            'image_profile'=>$imageProfile,
            'issues'=>$issues,
        ];
    }

    public function plannerDirective(string $prompt): array
    {
        $context=$this->context($prompt);
        return [
            'context'=>$context,
            'directive'=>"PAGE COMPOSITION CONTRACT\n".json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        ];
    }

    private function matchGroup(string $query,array $groups): ?string
    {
        $best=null;$score=0;
        foreach($groups as $key=>$group){
            $hits=0;
            foreach($group['aliases']??[] as $alias){
                $alias=Str::lower((string)$alias);
                if($alias!=='' && Str::contains($query,$alias))$hits+=strlen($alias)+10;
            }
            if($hits>$score){$score=$hits;$best=(string)$key;}
        }
        return $best;
    }

    private function inferBroadIndustry(string $q): string
    {
        if(Str::contains($q,['hotel','resort','hospitality']))return 'hospitality';
        if(Str::contains($q,['restaurant','cafe','coffee','bakery','dining']))return 'restaurant';
        if(Str::contains($q,['automotive','mechanic','vehicle','car service']))return 'automotive';
        if(Str::contains($q,['construction','builder','contractor']))return 'construction';
        if(Str::contains($q,['saas','software','technology',' app ',' ai ']))return 'technology';
        if(Str::contains($q,['medical','clinic','dental','dentist','wellness']))return 'health';
        return 'professional-services';
    }

    private function sectionRole(string $section): string
    {
        $s=Str::lower($section);
        return match(true){
            Str::startsWith($s,['hero_','mini_hero_'])=>'hero',
            Str::contains($s,['testimonial','review','stats','achievement','client_logo'])=>'proof',
            Str::contains($s,['contact','lead_','cta_','image_cta','booking'])=>'conversion',
            Str::contains($s,['pricing','package'])=>'pricing',
            Str::contains($s,['service','feature','menu','dish','room_collection','treatment','program'])=>'services',
            Str::contains($s,['portfolio','case_stud','gallery','project'])=>'gallery',
            Str::contains($s,['process','timeline','workflow','steps'])=>'process',
            Str::contains($s,['team','leadership'])=>'team',
            Str::contains($s,['about','story','mission','values'])=>'story',
            Str::contains($s,['faq'])=>'faq',
            default=>'value',
        };
    }

    private function roleSatisfied(string $required,array $roles): bool
    {
        if(in_array($required,$roles,true))return true;
        if($required==='value' && array_intersect($roles,['services','story','gallery']))return true;
        if($required==='contact' && array_intersect($roles,['contact','conversion']))return true;
        if($required==='conversion' && array_intersect($roles,['conversion','contact','pricing']))return true;
        return false;
    }

    private function isDense(string $section): bool
    {
        return Str::contains(Str::lower($section),['cards','grid','comparison','pricing','bento','faq']);
    }
}
