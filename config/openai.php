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

    // Patch 16.1: ultra-light first-stage model used only for business/visual intent.
    // It intentionally runs before Spark planning so Patch 16.2 can dispatch the
    // media-pack queue as soon as these keywords are available.
    'visual_model' => env('OPENAI_VISUAL_MODEL', env('OPENAI_PLANNER_MODEL', env('OPENAI_MODEL', 'gpt-5-mini'))),

    // Each pipeline stage owns its retry boundary so a transient failure in one
    // stage does not require restarting the whole AI generation pipeline.
    'pipeline_stage_attempts' => (int) env('OPENAI_PIPELINE_STAGE_ATTEMPTS', 2),
    'pipeline_retry_delay_ms' => (int) env('OPENAI_PIPELINE_RETRY_DELAY_MS', 150),

    // Patch 16.2: image downloads start immediately after visual analysis on a
    // dedicated high-priority queue. Run multiple workers against this queue
    // in production for parallel background media preparation.
    'media_pack_queue' => env('COSMIC_IMAGE_QUEUE', 'images-high'),
    'media_pack_initial_target' => (int) env('COSMIC_IMAGE_INITIAL_TARGET', 6),

    // Builder previews hotlink Unsplash and defer local asset capture until the
    // user commits the page (trial Save, purchase, or logged-in Publish). The
    // legacy trial env remains the fallback for backwards-compatible deploys.
    'trial_remote_images_enabled' => (bool) env('COSMIC_TRIAL_REMOTE_IMAGES', true),
    'remote_preview_images_enabled' => (bool) env('COSMIC_REMOTE_PREVIEW_IMAGES', env('COSMIC_TRIAL_REMOTE_IMAGES', true)),
    // Registered Builder generation uses the same remote Unsplash preview model
    // as /start. Provider results remain remote URLs; local industry media is
    // fallback-only and customer uploads continue to use local storage.
    'registered_remote_images_enabled' => (bool) env(
        'COSMIC_REGISTERED_REMOTE_IMAGES',
        env('COSMIC_REMOTE_PREVIEW_IMAGES', env('COSMIC_TRIAL_REMOTE_IMAGES', true))
    ),

    // Patch 16.3: independent branch coordinator. Images never block the AI
    // branch; branch diagnostics are returned for progress/benchmarking.
    'parallel_engine_enabled' => env('COSMIC_PARALLEL_ENGINE_ENABLED', true),

    // Patch 4.1.0.4: the second AI call can use a stronger model without
    // changing the lightweight Spark planner model.
    'content_model' => env('OPENAI_CONTENT_MODEL', env('OPENAI_MODEL', 'gpt-5-mini')),

    // Retry only when the model response cannot be decoded as the required
    // JSON envelope. API/transport failures still bubble to the controller.
    'content_json_attempts' => (int) env('OPENAI_CONTENT_JSON_ATTEMPTS', 2),


    // Patch 16.4: central AI cache. Visual intent, Spark planning and schema
    // selection are safe reusable stages. Small TTL jitter prevents many hot
    // keys from expiring simultaneously under load.
    'visual_cache_enabled' => env('OPENAI_VISUAL_CACHE_ENABLED', true),
    'visual_cache_ttl' => (int) env('OPENAI_VISUAL_CACHE_TTL', 86400),
    'schema_cache_enabled' => env('OPENAI_SCHEMA_CACHE_ENABLED', true),
    'schema_cache_ttl' => (int) env('OPENAI_SCHEMA_CACHE_TTL', 86400),
    'ai_cache_jitter_percent' => (int) env('OPENAI_AI_CACHE_JITTER_PERCENT', 10),

    // Patch 4.1.0.6: cache identical planning requests to reduce latency and
    // planner API usage. The key includes the planner model and registry.
    'planner_cache_enabled' => env('OPENAI_PLANNER_CACHE_ENABLED', true),
    'planner_cache_ttl' => (int) env('OPENAI_PLANNER_CACHE_TTL', 86400),

    // Content caching remains opt-in only. Reusing finished copy can make two
    // customer sites read too similarly; the default cache focuses on planning
    // and schema/template work instead.
    'content_cache_enabled' => env('OPENAI_CONTENT_CACHE_ENABLED', false),
    'content_cache_ttl' => (int) env('OPENAI_CONTENT_CACHE_TTL', 3600),
];
