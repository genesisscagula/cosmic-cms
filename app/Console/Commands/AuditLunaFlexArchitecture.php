<?php

namespace App\Console\Commands;

use App\Services\LunaAiFlexSparkService;
use App\Services\LunaFullPageComposerService;
use App\Services\LunaModelDepartmentService;
use App\Services\LunaSiteBundlePlannerService;
use Illuminate\Console\Command;

final class AuditLunaFlexArchitecture extends Command
{
    protected $signature='cosmic:audit-luna-flex';
    protected $description='Audit Luna/Terra/Sol routing, bundle custom slots and AI Flex/full-page composition wiring.';

    public function handle(): int
    {
        $checks=[
            'Luna/Terra/Sol departments'=>class_exists(LunaModelDepartmentService::class),
            'AI Flex section service'=>class_exists(LunaAiFlexSparkService::class),
            'Sol full-page composer'=>class_exists(LunaFullPageComposerService::class),
            'Bundle planner custom slots'=>str_contains((string)file_get_contents(app_path('Services/LunaSiteBundlePlannerService.php')),'custom_slots'),
            'Registered-first Flex fallback'=>str_contains((string)file_get_contents(app_path('Services/LunaSiteBundlePlannerService.php')),'registered_first_flex_fallback'),
            'Page chat Sol composer executor'=>str_contains((string)file_get_contents(app_path('Http/Controllers/CustomSparkController.php')),'sol_full_page_v1'),
        ];
        foreach($checks as $label=>$pass) $this->line(($pass?'PASS':'FAIL')."  {$label}");
        if(in_array(false,$checks,true)) return self::FAILURE;
        $models=app(LunaModelDepartmentService::class)->map();
        $this->table(['Department','Model'],collect($models)->map(fn($m,$d)=>[$d,$m])->values()->all());
        $this->info('PASS: Cosmic AI Flex architecture is wired for bundle slots and Sol full-page composition.');
        return self::SUCCESS;
    }
}
