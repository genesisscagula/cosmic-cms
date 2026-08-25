<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class AuditSparkTailwindOwnership extends Command
{
    protected $signature = 'cosmic:audit-tailwind-ownership {--strict : Exit non-zero when a CSS ownership regression is found}';
    protected $description = 'Ensure schema-backed Spark Tailwind classes are not overridden by Builder/appearance compatibility CSS.';

    public function handle(): int
    {
        $appCss=(string)@file_get_contents(resource_path('css/app.css'));
        $contractCss=(string)@file_get_contents(resource_path('css/cosmic-render-contract.css'));
        $builder=(string)@file_get_contents(resource_path('js/Pages/Websites/Builder.jsx'));

        $failures=[];

        if(preg_match('/cosmic-builder-(?:spark|canvas)[^{]{0,500}\{[^}]*border-radius\s*:\s*100px\s*!important/is',$appCss.$builder)){
            $failures[]='forced_100px_customer_button_radius';
        }

        if(str_contains($builder,'Clean Page Style button contract')){
            $failures[]='clean_page_style_customer_button_override';
        }

        if(!str_contains($builder,'data-cosmic-preview-isolation="true"')
            || !str_contains($builder,'cosmic-builder-canvas cosmic-preview-isolation')){
            $failures[]='builder_canvas_missing_appearance_isolation';
        }

        foreach([$appCss,$contractCss] as $source){
            if(preg_match_all('/([^{}]*\.cosmic-render-shell[^{}]*)\{([^{}]*)\}/is',$source,$matches,PREG_SET_ORDER)){
                foreach($matches as $match){
                    $selector=preg_replace('/\s+/',' ',trim((string)$match[1]));
                    $body=(string)$match[2];
                    if(!str_contains($body,'!important')) continue;
                    if(!preg_match('/(?:font-size|font-weight|line-height|letter-spacing|border-radius|box-shadow|background(?:-color)?|color|padding|margin|max-width|object-fit)\s*:/i',$body)) continue;
                    if(str_contains($selector,'cosmic-tw-slot--')) continue;

                    // Variable-only shell declarations are not customer element overrides.
                    if(trim($selector)==='.cosmic-render-shell') continue;

                    $failures[]='render_shell_override_without_schema_exclusion: '.$selector;
                    if(count($failures)>=20) break 2;
                }
            }
        }

        if($failures===[]){
            $this->info('Spark Tailwind ownership audit passed.');
            return self::SUCCESS;
        }

        foreach($failures as $failure) $this->error($failure);
        $this->warn(count($failures).' Tailwind ownership regression(s) found.');
        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
