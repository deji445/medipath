<?php

return [
    'assessment' => [
        'driver' => env('MEDIPATH_ASSESSMENT_DRIVER', 'deterministic'),
        'max_questions' => (int) env('MEDIPATH_ASSESSMENT_MAX_QUESTIONS', 10),
        'initial_ai_safety_screen' => (bool) env('MEDIPATH_INITIAL_AI_SAFETY_SCREEN', false),
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_ASSESSMENT_MODEL', 'gemini-3.8-flash'),
            'endpoint' => env('GEMINI_API_ENDPOINT', 'https://generativelanguage.googleapis.com/v1/interactions'),
            'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 5),
            'timeout' => (int) env('GEMINI_TIMEOUT', 20),
            'retry_delay_ms' => (int) env('GEMINI_RETRY_DELAY_MS', 250),
        ],
    ],
    'emergency' => [
        'contact_label' => env('MEDIPATH_EMERGENCY_CONTACT_LABEL', 'Ghana National Ambulance Service'),
        'phone' => env('MEDIPATH_EMERGENCY_PHONE', '112'),
    ],
    'routing' => [
        'geocoding' => [
            'enabled' => (bool) env('MEDIPATH_GEOCODING_ENABLED', true),
            'endpoint' => env('MEDIPATH_GEOCODING_ENDPOINT', 'https://nominatim.openstreetmap.org/search'),
            'user_agent' => env('MEDIPATH_GEOCODING_USER_AGENT', 'MediPath-AI-Prototype/1.0'),
        ],
    ],
    'demo_staff_password' => env('MEDIPATH_DEMO_STAFF_PASSWORD'),
];
