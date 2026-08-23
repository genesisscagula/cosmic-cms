<?php

namespace App\Services;

final class LunaDesignCriticService
{
    private array $rules;

    public function __construct()
    {
        $path=resource_path('luna/design_critic.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->rules=is_array($decoded)?$decoded:[];
    }

    public function critique(array $plan,array $siteDna=[],array $registeredKeys=[]): array
    {
        $sections=array_values(array_filter((array)($plan['sections']??[]),fn($v)=>is_string($v)&&trim($v)!==''));
        $registered=array_flip($registeredKeys);
        $findings=[];
        $recommended=$sections;

        foreach($sections as $i=>$key){
            if($registeredKeys!==[] && !isset($registered[$key])){
                $findings[]=$this->finding('composition','critical',"Unregistered Spark '{$key}' at position {$i}.",'Replace it with a registered Spark.');
            }
        }

        if(count($sections)<3){
            $findings[]=$this->finding('composition','high','The page composition is too thin for a complete premium page.','Use enough distinct sections to establish context, proof/value, and a closing action.');
        } elseif(count($sections)>10){
            $findings[]=$this->finding('hierarchy','medium','The page composition is unusually long.','Remove redundant sections unless the requested page genuinely needs the depth.');
        }

        $families=array_map(fn($key)=>$this->family($key),$sections);
        for($i=1;$i<count($families);$i++){
            if($families[$i]!=='' && $families[$i]===$families[$i-1]){
                $findings[]=$this->finding('composition','medium',"Adjacent sections {$i} and ".($i+1)." repeat the '{$families[$i]}' layout role.",'Separate repeated roles with a complementary section type.');
            }
        }

        $counts=array_count_values(array_filter($families));
        foreach($counts as $family=>$count){
            if($count>=3){
                $findings[]=$this->finding('consistency','medium',"The '{$family}' role appears {$count} times.",'Reduce repetition so the page feels intentionally composed rather than templated.');
            }
        }

        $hasHero=in_array('hero',$families,true);
        if(!$hasHero){
            $findings[]=$this->finding('hierarchy','high','No clear hero/banner role is present.','Open with a registered hero/banner Spark unless the page intent explicitly calls for another opening.');
        } elseif(($families[0]??'')!=='hero'){
            $findings[]=$this->finding('hierarchy','medium','The hero/banner is not the opening section.','Move the hero/banner to the first position when appropriate.');
            $heroIndex=array_search('hero',$families,true);
            if($heroIndex!==false){
                $hero=$recommended[$heroIndex];
                array_splice($recommended,$heroIndex,1);
                array_unshift($recommended,$hero);
            }
        }

        $hasClosingCta=count($families)>0 && in_array(end($families),['cta','contact'],true);
        if(!$hasClosingCta && !in_array((string)($plan['page_intent']??''),['legal','privacy','terms'],true)){
            $findings[]=$this->finding('conversion','medium','The composition has no clear closing CTA/contact role.','Close the journey with an appropriate registered CTA/contact Spark.');
        }

        $mediaDirection=trim((string)($plan['media_direction']??''));
        if($mediaDirection===''){
            $findings[]=$this->finding('media','low','The design plan has no explicit media direction.','Keep imagery relevant to the industry and avoid repeating the same visual across sections.');
        }

        $dnaTheme=trim((string)($siteDna['theme_family']??''));
        $planTheme=trim((string)($plan['theme']??''));
        $themeAction=(string)($plan['theme_action']??'');
        if($dnaTheme!=='' && $planTheme!=='' && $dnaTheme!==$planTheme && $themeAction!=='replace'){
            $findings[]=$this->finding('consistency','high',"Planned theme '{$planTheme}' conflicts with established Site DNA '{$dnaTheme}'.",'Preserve the established theme unless the user explicitly requested a rebrand.');
        }

        $responsiveRisks=(array)($plan['responsive_risks']??[]);
        if(count($responsiveRisks)>=3){
            $findings[]=$this->finding('responsive','medium','The planned composition contains several known responsive-risk sections.','Prefer a lower-risk registered alternative where it preserves the requested design direction.');
        }

        $score=$this->score($findings);
        $pass=$score>=(int)($this->rules['thresholds']['pass']??82)
            && !collect($findings)->contains(fn($f)=>in_array($f['severity'],['critical','high'],true));

        return [
            'mode'=>'preflight_critic',
            'score'=>$score,
            'grade'=>$this->grade($score),
            'pass'=>$pass,
            'decision'=>$pass?'accept':($score>=(int)($this->rules['thresholds']['revise']??68)?'revise':'reject'),
            'findings'=>$findings,
            'recommended_sections'=>array_values($recommended),
            'auto_revision_allowed'=>true,
            'max_revision_passes'=>1,
            'preserve_user_requirements'=>true,
            'preserve_site_dna'=>true,
        ];
    }

    private function family(string $key): string
    {
        $key=strtolower($key);
        foreach([
            'hero'=>['hero','banner'],
            'services'=>['service'],
            'features'=>['feature','benefit'],
            'proof'=>['testimonial','review','logo','trust','stat'],
            'process'=>['process','timeline','step'],
            'pricing'=>['pricing','price'],
            'gallery'=>['gallery','portfolio','project','work'],
            'team'=>['team','people'],
            'faq'=>['faq'],
            'contact'=>['contact','form'],
            'cta'=>['cta','call_to_action'],
        ] as $family=>$needles){
            foreach($needles as $needle) if(str_contains($key,$needle)) return $family;
        }
        return 'content';
    }

    private function finding(string $dimension,string $severity,string $message,string $recommendation): array
    {
        return compact('dimension','severity','message','recommendation');
    }

    private function score(array $findings): int
    {
        $penalty=['critical'=>28,'high'=>16,'medium'=>8,'low'=>3];
        $score=100;
        foreach($findings as $f)$score-=($penalty[$f['severity']]??5);
        return max(0,min(100,$score));
    }

    private function grade(int $score): string
    {
        return match(true){$score>=92=>'A',$score>=82=>'B',$score>=68=>'C',$score>=55=>'D',default=>'F'};
    }
}
