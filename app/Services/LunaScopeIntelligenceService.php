<?php

namespace App\Services;

use Illuminate\Support\Str;

final class LunaScopeIntelligenceService
{
    private array $rules;

    public function __construct()
    {
        $path=resource_path('luna/scope_intelligence.json');
        $decoded=is_file($path)?json_decode((string)file_get_contents($path),true):[];
        $this->rules=is_array($decoded)?$decoded:[];
    }

    public function resolve(string $prompt,string $uiScope='page',array $elementContext=[]): array
    {
        $q=Str::lower(trim($prompt));
        $explicit=$this->explicitScope($q);
        $semantic=$this->semanticTarget($q,$elementContext);

        $scope=$explicit;
        if($scope===null){
            if($semantic['kind']==='item')$scope='item';
            elseif($semantic['kind']==='element')$scope='element';
            else $scope=$this->normalizeUiScope($uiScope,$elementContext);
        }

        // "all/every <primitive>" prefers centralized tokens over looping Sparks.
        $globalToken=$this->globalToken($q);
        if($globalToken!==null){
            $scope='global_token';
        }

        return [
            'scope'=>$scope,
            'explicit'=>$explicit!==null || $globalToken!==null,
            'ui_scope'=>$uiScope,
            'semantic_target'=>$semantic,
            'global_token'=>$globalToken,
            'item_index'=>$this->itemIndex($elementContext),
            'section_index'=>isset($elementContext['sectionIndex'])?(int)$elementContext['sectionIndex']:null,
            'rules'=>$this->rules['rules']??[],
        ];
    }

    public function plannerDirective(array $resolution): string
    {
        $safe=[
            'scope'=>$resolution['scope']??'page',
            'explicit'=>$resolution['explicit']??false,
            'semantic_target'=>$resolution['semantic_target']??[],
            'global_token'=>$resolution['global_token']??null,
            'item_index'=>$resolution['item_index']??null,
        ];
        return "SCOPE CONTRACT\n".json_encode($safe,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
            ."\nNever mutate outside this resolved scope. Prefer centralized tokens for global_token scope.";
    }

    private function explicitScope(string $q): ?string
    {
        $patterns=$this->rules['patterns']??[];
        // Narrowest explicit wording wins.
        foreach(['element','item','section','page','site'] as $scope){
            foreach($patterns[$scope]??[] as $phrase){
                if(Str::contains($q,Str::lower((string)$phrase)))return $scope;
            }
        }
        return null;
    }

    private function globalToken(string $q): ?string
    {
        $map=[
            'heading'=>['all headings','every heading','all h1','all h2','all h3','all h4','all h5','all h6'],
            'button'=>['all buttons','every button'],
            'card'=>['all cards','every card'],
            'image'=>['all images','every image'],
            'section'=>['all sections','every section'],
        ];
        foreach($map as $token=>$phrases){
            if(Str::contains($q,$phrases))return $token;
        }
        return null;
    }

    private function semanticTarget(string $q,array $elementContext): array
    {
        $type=Str::lower(trim((string)($elementContext['type']??'')));
        if(Str::contains($q,['this card','this item','this service','this testimonial','this pricing card'])){
            return ['kind'=>'item','type'=>$type!==''?$type:'card'];
        }
        if(Str::contains($q,['this heading','this title','this headline','this text','this button','this image','this link','this logo'])){
            return ['kind'=>'element','type'=>$type!==''?$type:$this->inferElementType($q)];
        }
        if(isset($elementContext['itemIndex']))return ['kind'=>'item','type'=>$type!==''?$type:'card'];
        if($type!=='')return ['kind'=>'element','type'=>$type];
        return ['kind'=>null,'type'=>null];
    }

    private function inferElementType(string $q): string
    {
        foreach(['heading','button','image','link','logo','text'] as $type){
            if(Str::contains($q,$type))return $type;
        }
        return 'element';
    }

    private function itemIndex(array $context): ?int
    {
        foreach(['itemIndex','item_index','cardIndex','card_index'] as $key){
            if(isset($context[$key]) && is_numeric($context[$key]))return (int)$context[$key];
        }
        return null;
    }

    private function normalizeUiScope(string $scope,array $context): string
    {
        if(isset($context['itemIndex'])||isset($context['item_index']))return 'item';
        if(trim((string)($context['type']??''))!=='')return 'element';
        return in_array($scope,['page','section','header','footer'],true)?$scope:'page';
    }
}
