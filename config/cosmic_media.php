<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Remote provider image publishing
    |--------------------------------------------------------------------------
    |
    | V1 keeps Unsplash/Pexels image URLs remote throughout Builder save,
    | purchase/provisioning, publish and static export. Customer-uploaded
    | assets continue to use local storage normally.
    |
    | Set COSMIC_LOCALIZE_REMOTE_IMAGES=true later if automatic provider-image
    | capture/localization is intentionally re-enabled.
    |
    */
    'localize_remote_images' => (bool) env('COSMIC_LOCALIZE_REMOTE_IMAGES', false),
];
