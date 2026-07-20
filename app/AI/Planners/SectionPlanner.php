<?php

namespace App\AI\Planners;

use App\AI\Clients\OpenAIClient;
use App\AI\Prompts\PlannerPrompt;
use App\AI\Registries\SectionRegistry;

class SectionPlanner
{
    protected OpenAIClient $client;

    public function __construct()
    {
        $this->client = new OpenAIClient();
    }

    public function plan(string $userPrompt): array
    {
        // Get all available sections
        $sections = SectionRegistry::all();

        // Build AI prompt
        $prompt = PlannerPrompt::build(
            $userPrompt,
            $sections
        );

        // Ask OpenAI
        $response = $this->client->chat(
            $prompt["system"],
            $prompt["user"]
        );

        // Convert JSON string to PHP array
        $selected = json_decode($response, true);

        // Safety fallback
        if (!is_array($selected)) {
            return [];
        }

        return $selected;
    }
}