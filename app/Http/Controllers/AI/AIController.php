<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\AI\Planners\SectionPlanner;
use App\AI\Generators\ContentGenerator;


class AIController extends Controller
{

    public function generateContent(Request $request)
    {

        $generator = new ContentGenerator();

        $blocks = $generator->generate(

            $request->prompt,

            $request->sections

        );

        return response()->json([

            "blocks"=>$blocks

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