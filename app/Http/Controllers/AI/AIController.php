<?php

namespace App\Http\Controllers\AI;

use App\AI\Generators\ContentGenerator;
use App\AI\Generators\ImageGenerator;
use App\AI\Layouts\LayoutEngine;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AIController extends Controller
{
    public function generateContent(Request $request)
    {
        $generator = new ContentGenerator();
        $imageGenerator = new ImageGenerator();

        $sections = LayoutEngine::random(
            strtolower($request->input('image_folder', 'default'))
        );

        $content = $generator->generate(
            $request->prompt,
            $sections
        );

        foreach ($content['blocks'] as &$block) {

            if (!isset($block['image_url'])) {
                continue;
            }

            $block['image_url'] = $imageGenerator->generate(
                $content['image_folder'],
                $block
            );
        }

        return response()->json($content);
    }

    public function selectSections(Request $request)
    {
        return response()->json([
            'sections' => LayoutEngine::random(
                strtolower($request->input('image_folder', 'default'))
            )
        ]);
    }
}