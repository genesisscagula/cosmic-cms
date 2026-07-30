<?php

namespace App\AI\Clients;

use OpenAI\Laravel\Facades\OpenAI;

class OpenAIClient
{
    public function chat($system, $user)
    {
        $response = OpenAI::chat()->create([

            "model" => env("OPENAI_MODEL", "gpt-5-mini"),

            // Uncomment only if your API/model supports it.
            // "temperature" => 0.8,

            "messages" => [

                [
                    "role" => "system",
                    "content" => $system
                ],

                [
                    "role" => "user",
                    "content" => $user
                ]

            ],

        ]);

        return $response->choices[0]->message->content;
    }
}