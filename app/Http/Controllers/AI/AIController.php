<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\AI\Planners\SectionPlanner;
use App\AI\Generators\ContentGenerator;
use App\AI\Generators\ImageGenerator;


class AIController extends Controller
{

    public function generateContent(Request $request)
    {
        $generator = new ContentGenerator();
        $imageGenerator = new ImageGenerator();

        $content = $generator->generate(
            $request->prompt,
            $request->sections
        );

        $imageFolder = $content['image_folder'];
        $blocks = $content['blocks'];

        // Generate local images
        foreach ($blocks as &$block) {

            if (isset($block['image_url'])) {

                $block['image_url'] = $imageGenerator->generate(
                    $imageFolder,
                    $block
                );

            }

        }

        return response()->json([
            "image_folder" => $imageFolder,
            "blocks" => $blocks
        ]);
    }


    public function selectSections(Request $request)
    {
        $planner = new SectionPlanner();

        $sections = $planner->plan(
            $request->input('prompt')
        );

        return response()->json([
            "sections" => $sections
        ]);
    }

    
}