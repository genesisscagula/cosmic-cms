<?php

namespace App\Console\Commands;

use App\Services\LunaActionContractRegistry;
use Illuminate\Console\Command;

final class AuditLunaActionContracts extends Command
{
    protected $signature = 'cosmic:audit-luna-action-contracts {--strict : Exit non-zero when a required contract is missing}';
    protected $description = 'Audit the shared Luna action-contract registry and Spark compatibility contract.';

    public function handle(LunaActionContractRegistry $contracts): int
    {
        $requiredScopes = [
            'sparks','global','header','footer','navigation','theme','page','publish',
            'media','posts','commerce','seo','settings','navigate',
        ];
        $requiredSparkActions = [
            'edit_spark','change_spark','add_spark','remove_spark',
            'custom_spark','reference_spark','reorder_spark',
        ];
        $requiredGlobalActions = [
            'edit_global','reset_global','typography','colors','spacing','buttons',
            'container','backgrounds','custom_global',
        ];
        $requiredThemeActions = [
            'change_theme','edit_theme','brand_theme','custom_theme','reset_theme','theme_from_logo',
        ];
        $requiredHeaderActions = [
            'edit_header','change_header','overlay_header','sticky_header','transparent_header',
            'logo','header_cta','header_spacing','mobile_header','custom_header',
        ];
        $requiredFooterActions = [
            'edit_footer','change_footer','mega_footer','simple_footer','footer_columns',
            'footer_cta','footer_socials','footer_brand','footer_background','custom_footer',
        ];

        $failed = [];
        foreach ($requiredScopes as $scope) {
            if (! $contracts->hasScope($scope)) $failed[] = "scope:{$scope}";
        }
        foreach ($requiredSparkActions as $action) {
            if (! $contracts->hasAction('sparks', $action)) $failed[] = "sparks:{$action}";
        }
        foreach ($requiredGlobalActions as $action) {
            if (! $contracts->hasAction('global', $action)) $failed[] = "global:{$action}";
        }
        foreach ($requiredThemeActions as $action) {
            if (! $contracts->hasAction('theme', $action)) $failed[] = "theme:{$action}";
        }
        foreach ($requiredHeaderActions as $action) {
            if (! $contracts->hasAction('header', $action)) $failed[] = "header:{$action}";
        }
        foreach ($requiredFooterActions as $action) {
            if (! $contracts->hasAction('footer', $action)) $failed[] = "footer:{$action}";
        }

        foreach (['sparks'=>$requiredSparkActions,'global'=>$requiredGlobalActions,'theme'=>$requiredThemeActions,'header'=>$requiredHeaderActions,'footer'=>$requiredFooterActions] as $scope=>$actions) {
            foreach ($actions as $action) {
                $meta = $contracts->routingMetadata($scope, $action);
                if (($meta['executor'] ?? '') === '' || ($meta['mutation'] ?? '') === '') {
                    $failed[] = "metadata:{$scope}:{$action}";
                }
            }
        }

        if ($failed === []) {
            $this->info('Luna action-contract registry audit passed.');
            $this->line('Scopes: '.implode(', ', $contracts->scopes()));
            $this->line('Spark actions: '.implode(', ', $contracts->actions('sparks')));
            $this->line('Global actions: '.implode(', ', $contracts->actions('global')));
            $this->line('Theme actions: '.implode(', ', $contracts->actions('theme')));
            $this->line('Header actions: '.implode(', ', $contracts->actions('header')));
            $this->line('Footer actions: '.implode(', ', $contracts->actions('footer')));
            return self::SUCCESS;
        }

        foreach ($failed as $item) $this->error('Missing/invalid contract: '.$item);
        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
