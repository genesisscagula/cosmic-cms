<?php

namespace App\AI\Generators;

class ImageGenerator
{
    /**
     * Generate a random local image from the selected AI folder.
     */
    public function generate(string $imageFolder, array $block): string
    {
        $imageFolder = strtolower(trim($imageFolder));

        logger()->info('[ImageGenerator] Using Folder', [
            'folder' => $imageFolder,
        ]);

        $images = glob(
            storage_path("app/public/cms-images/{$imageFolder}/*.{jpg,jpeg,png,webp,avif}"),
            GLOB_BRACE
        );

        // Fallback to construction if folder is empty or doesn't exist
        if (empty($images)) {

            logger()->warning('[ImageGenerator] Folder empty, using construction', [
                'folder' => $imageFolder,
            ]);

            $imageFolder = 'construction';

            $images = glob(
                storage_path("app/public/cms-images/construction/*.{jpg,jpeg,png,webp,avif}"),
                GLOB_BRACE
            );
        }

        // No local images at all
        if (empty($images)) {

            logger()->error('[ImageGenerator] No local images found');

            return 'https://picsum.photos/1600/900';

        }

        $image = $images[array_rand($images)];

        logger()->info('[ImageGenerator] Selected Image', [
            'folder' => $imageFolder,
            'image'  => basename($image),
        ]);

        return asset(
            "storage/cms-images/{$imageFolder}/" . basename($image)
        ) . '?v=' . filemtime($image);
    }
}