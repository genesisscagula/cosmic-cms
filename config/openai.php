<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key and Organization
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API Key and organization. This will be
    | used to authenticate with the OpenAI API - you can find your API key
    | and organization on your OpenAI dashboard, at https://openai.com.
    */

    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Project
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API project. This is used optionally in
    | situations where you are using a legacy user API key and need association
    | with a project. This is not required for the newer API keys.
    */
    'project' => env('OPENAI_PROJECT'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Base URL
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API base URL used to make requests. This
    | is needed if using a custom API endpoint. Defaults to: api.openai.com/v1
    */
    'base_uri' => env('OPENAI_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout may be used to specify the maximum number of seconds to wait
    | for a response. Full-page generation may require longer than 30 seconds.
    */

    // Keep this below the local PHP request limit. A timeout must be returned
    // to the controller so a trial can be marked as failed instead of leaving
    // the public generation screen waiting for a request PHP has terminated.
    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 180),

    // Spark selection can use a faster/cheaper model independently from
    // the full content generator. Falls back to OPENAI_MODEL when omitted.
    'planner_model' => env('OPENAI_PLANNER_MODEL', env('OPENAI_MODEL', 'gpt-5-mini')),

    // Patch 4.1.0.4: the second AI call can use a stronger model without
    // changing the lightweight Spark planner model.
    'content_model' => env('OPENAI_CONTENT_MODEL', env('OPENAI_MODEL', 'gpt-5-mini')),

    // Retry only when the model response cannot be decoded as the required
    // JSON envelope. API/transport failures still bubble to the controller.
    'content_json_attempts' => (int) env('OPENAI_CONTENT_JSON_ATTEMPTS', 2),

    // Patch 4.1.0.6: cache identical planning requests to reduce latency and
    // planner API usage. The key includes the planner model and registry.
    'planner_cache_enabled' => env('OPENAI_PLANNER_CACHE_ENABLED', true),
    'planner_cache_ttl' => (int) env('OPENAI_PLANNER_CACHE_TTL', 86400),

    // Content caching is intentionally configurable. When enabled, identical
    // prompt + selected Spark + model requests reuse validated copy while the
    // Smart Image layer still assigns images for the current generation.
    'content_cache_enabled' => env('OPENAI_CONTENT_CACHE_ENABLED', true),
    'content_cache_ttl' => (int) env('OPENAI_CONTENT_CACHE_TTL', 3600),
];
