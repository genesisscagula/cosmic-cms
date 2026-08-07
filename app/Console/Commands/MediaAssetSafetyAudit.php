<?php

namespace App\Console\Commands;

use App\Models\Website;
use App\Services\MediaAssetSafetyService;
use Illuminate\Console\Command;

class MediaAssetSafetyAudit extends Command
{
    protected $signature = 'cosmic:media-safety-audit {--website= : Audit one website ID} {--strict : Return failure when provider URLs are found}';
    protected $description = 'Audit draft and published website content for remote Unsplash/Pexels image URLs.';

    public function handle(MediaAssetSafetyService $safety): int
    {
        $query = Website::query();
        if ($id = $this->option('website')) {
            $query->whereKey((int) $id);
        }

        $unsafe = 0;
        $query->orderBy('id')->chunkById(100, function ($websites) use ($safety, &$unsafe): void {
            foreach ($websites as $website) {
                $draft = $safety->draftProviderUrls($website);
                $published = $safety->publishedProviderUrls($website);
                if ($draft === [] && $published === []) continue;

                $unsafe++;
                $this->warn(sprintf(
                    'Website %d: draft_remote=%d published_remote=%d',
                    $website->id,
                    count($draft),
                    count($published),
                ));
            }
        });

        if ($unsafe === 0) {
            $this->info('Media safety audit passed: no remote provider URLs found in audited websites.');
            return self::SUCCESS;
        }

        $this->warn("Media safety audit found {$unsafe} website(s) with remote provider URLs.");
        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
