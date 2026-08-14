<?php

return [
    'version' => 1,

    // Website Health is intentionally read-only. These values only tune warnings/scoring.
    'large_image_bytes' => (int) env('COSMIC_HEALTH_LARGE_IMAGE_BYTES', 2 * 1024 * 1024),

    'severity_penalties' => [
        'critical' => 15,
        'warning' => 4,
        'info' => 1,
    ],
];
