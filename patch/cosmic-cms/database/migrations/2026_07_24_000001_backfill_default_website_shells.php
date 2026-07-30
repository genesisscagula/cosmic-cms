<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('websites')
            ->select(['id', 'name', 'global_header', 'global_footer'])
            ->orderBy('id')
            ->chunkById(100, function ($websites): void {
                foreach ($websites as $website) {
                    $updates = [];

                    if ($website->global_header === null) {
                        $updates['global_header'] = json_encode([
                            'type' => 'glassmorphism_header',
                            'logo_text' => $website->name,
                            'cta_label' => 'Get Started',
                            'menu' => [
                                ['label' => 'Home', 'url' => '#'],
                                ['label' => 'About', 'url' => '#'],
                                ['label' => 'Services', 'url' => '#'],
                            ],
                        ]);
                    }

                    if ($website->global_footer === null) {
                        $updates['global_footer'] = json_encode([
                            'type' => 'minimal_footer',
                            'logo_text' => $website->name,
                            'copyright' => '© ' . now()->year . '. All rights reserved.',
                        ]);
                    }

                    if ($updates !== []) {
                        $updates['updated_at'] = now();
                        DB::table('websites')->where('id', $website->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        // Defaults are customer-editable content, so rolling back must not remove them.
    }
};
