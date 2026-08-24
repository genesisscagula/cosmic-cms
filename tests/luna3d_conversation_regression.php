<?php
namespace App\Services { if(!function_exists(__NAMESPACE__.'\\mb_strtolower')) { function mb_strtolower($s){ return strtolower($s); } } }
namespace {
require_once __DIR__.'/../app/Services/LunaExecutionVerificationService.php';
require_once __DIR__.'/../app/Services/LunaContextResolverService.php';
use App\Services\LunaExecutionVerificationService;
use App\Services\LunaContextResolverService;

function ok($cond,$name){ if(!$cond){fwrite(STDERR,"FAIL {$name}\n"); exit(1);} echo "PASS {$name}\n"; }

$v=new LunaExecutionVerificationService();
$planned=[
 ['operation_id'=>'op_1','operation'=>'update','domain'=>'typography','scope'=>'section','target'=>['type'=>'heading','key'=>'hero.heading']],
 ['operation_id'=>'op_2','operation'=>'update','domain'=>'design','scope'=>'section','target'=>['type'=>'card','key'=>'services.cards']],
];
$applied=[['operation_id'=>'op_1','operation'=>'update','domain'=>'typography','scope'=>'section','target'=>['type'=>'heading','key'=>'hero.heading'],'verified'=>true]];
$r=$v->verify($planned,$applied,true);
ok($r['status']==='partial','compound partial');
ok($r['can_claim_complete']===false,'partial cannot claim complete');
ok(count($r['verified_plans'])===1,'only verified plan retained');

$r2=$v->verify([$planned[0]],[],false);
ok($r2['status']==='failed','failed mutation');
ok($r2['can_claim_complete']===false,'failed cannot claim complete');

$resolver=new LunaContextResolverService();
$prior=['current'=>['page_id'=>12,'selection'=>['type'=>'heading','key'=>'hero.heading']], 'last_verified_action'=>[
 'action'=>'update','domain'=>'typography','leaf_operation'=>'font_size','operation'=>'update','scope'=>'section',
 'target'=>['type'=>'heading','key'=>'hero.heading'],'changes'=>['relative_size'=>['direction'=>'decrease','amount'=>'slight']],
 'page_id'=>12,'before'=>['blocks_fingerprint'=>'abc'],'after'=>['blocks_fingerprint'=>'def']
]];
$x=$resolver->resolve('a little more',$prior,[]);
ok($x['execution_allowed']===true && $x['mode']==='repeat','repeat follows verified action');
$x=$resolver->resolve('too much',$prior,[]);
ok(($x['inherit']['changes']['relative_size']['direction']??'')==='increase','reverse flips direction');
$x=$resolver->resolve('same for this one',$prior,['selection'=>['type'=>'heading','key'=>'services.heading']]);
ok(($x['inherit']['target']['key']??'')==='services.heading','same-here retargets selection');
$sameApplied=$resolver->apply([
 'intent'=>'action','action'=>'update','domain'=>'typography','leaf_operation'=>'font_size','operation'=>'update','scope'=>'section',
 'target'=>['type'=>'heading','key'=>'hero.heading'],'changes'=>[],
 'operations'=>[['operation_id'=>'op_1','domain'=>'typography','leaf_operation'=>'font_size','operation'=>'update','scope'=>'section','target'=>['type'=>'heading','key'=>'hero.heading'],'changes'=>[]]],
],$x,'same for this heading');
ok(($sameApplied['target']['key']??'')==='services.heading','same-here apply prefers live selection');
ok(($sameApplied['operations'][0]['target']['key']??'')==='services.heading','same-here executable row uses live selection');
$x=$resolver->resolve('apply that everywhere',$prior,[]);
ok(($x['inherit']['scope']??'')==='site','widen scope');
$x=$resolver->resolve('only on mobile',$prior,[]);
ok(($x['inherit']['changes']['breakpoint']??'')==='mobile','breakpoint refinement');
$x=$resolver->resolve('undo that',$prior,[]);
ok($x['execution_allowed']===false && ($x['needs_clarification']??false)===true,'unsafe undo blocked without reversible snapshot');
$prior['last_verified_action']['target_stale']=true;
$x=$resolver->resolve('again',$prior,[]);
ok($x['execution_allowed']===false,'stale target blocked');

}
