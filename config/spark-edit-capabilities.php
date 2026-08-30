<?php

return [
    'version' => 1,
    'protected_fields' => ['type', '_renderKey', 'dynamic_binding', 'binding', 'source', 'query', 'luna_tailwind_schema'],
    'semantic_treatments' => ['auto', 'primary', 'white', 'surface', 'accent'],
    'modules' => [
        'Typography' => [
            'operations' => ['font_size', 'font_family', 'font_weight', 'line_height', 'letter_spacing', 'text_align'],
            'storage' => 'luna_typography_overrides',
            'relative' => ['font_size', 'font_weight', 'line_height', 'letter_spacing'],
        ],
        'Spacing' => [
            'operations' => ['padding', 'padding_top', 'padding_bottom', 'padding_left', 'padding_right', 'padding_x', 'gap', 'container_width', 'section_height'],
            'storage' => 'luna_section_overrides',
            'relative' => ['padding', 'padding_top', 'padding_bottom', 'padding_left', 'padding_right', 'padding_x', 'gap', 'section_height'],
        ],
        'Surface' => [
            'operations' => ['semantic_treatment', 'background', 'card_surface', 'border', 'border_radius', 'shadow'],
            'storage' => ['theme', 'resolvedTheme', 'luna_background_overrides', 'luna_component_overrides'],
            'semantic_only' => true,
        ],
        'Responsive' => [
            'operations' => ['typography', 'spacing', 'columns', 'visibility', 'alignment'],
            'breakpoints' => ['desktop', 'tablet', 'mobile'],
        ],
        'Visibility' => [
            'operations' => ['show', 'hide'],
            'storage' => 'luna_visibility_overrides',
        ],
        'Media' => [
            'operations' => ['replace_image', 'remove_image', 'generate_image', 'select_library_image', 'replace_video', 'remove_video', 'object_fit', 'position'],
            'field_patterns' => ['image', 'photo', 'avatar', 'poster', 'logo', 'video', 'media'],
        ],
        'Overlay' => [
            'operations' => ['overlay_color', 'overlay_opacity', 'overlay_mode'],
            'storage' => ['overlayOpacity', 'luna_background_overrides'],
            'relative' => ['overlay_opacity'],
        ],
        'GridLayout' => [
            'operations' => ['columns', 'layout_variant', 'alignment', 'container_width'],
            'relative' => ['columns'],
        ],
        'Buttons' => [
            'operations' => ['label', 'url', 'style', 'radius', 'size', 'alignment'],
            'field_patterns' => ['button', 'primary_label', 'primary_url', 'secondary_label', 'secondary_url', 'cta'],
        ],
        'ItemCollection' => [
            'operations' => ['add_item', 'remove_item', 'duplicate_item', 'reorder_items', 'update_item'],
            'collection_keys' => ['items', 'cards', 'services', 'features', 'steps', 'slides', 'images', 'testimonials', 'faqs', 'team', 'plans', 'logos', 'gallery'],
        ],
        'Dimensions' => [
            'operations' => ['width', 'height', 'min_height', 'aspect_ratio'],
            'relative' => ['width', 'height', 'min_height'],
        ],
    ],
];
