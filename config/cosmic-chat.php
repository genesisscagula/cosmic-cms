<?php

return [
    'enabled' => env('COSMIC_CHAT_ENABLED', true),
    'model' => env('COSMIC_CHAT_MODEL', env('OPENAI_MODEL', 'gpt-5-mini')),
    'max_user_chars' => 1000,
    'history_messages' => 12,
    'welcome' => 'Hi! I’m the Cosmic CMS assistant. Ask me about building a website, plans, features, or the free trial.',
    'unknown_reply' => 'I don’t have a verified Cosmic CMS answer for that yet. I can keep your question in this chat for the Cosmic CMS team to review.',
];
