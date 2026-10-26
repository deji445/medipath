<?php

return [
    'phone_verification' => [
        'driver' => env('MEDIPATH_PHONE_VERIFICATION_DRIVER', 'local'),
        'expiry_minutes' => (int) env('MEDIPATH_PHONE_VERIFICATION_EXPIRY_MINUTES', 10),
        'max_attempts' => (int) env('MEDIPATH_PHONE_VERIFICATION_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('MEDIPATH_PHONE_VERIFICATION_RESEND_COOLDOWN_SECONDS', 60),
        'demo_code' => env('MEDIPATH_PHONE_VERIFICATION_DEMO_CODE'),
        'bms' => [
            'endpoint' => env('BMS_SMS_ENDPOINT', 'https://api.mnotify.com/api/sms/quick'),
            'api_key' => env('BMS_API_KEY'),
            'sender_id' => env('BMS_SENDER_ID', 'medipath'),
            'connect_timeout' => (int) env('BMS_CONNECT_TIMEOUT', 5),
            'timeout' => (int) env('BMS_TIMEOUT', 10),
        ],
        'nalo' => [
            'endpoint' => env('NALO_SMS_ENDPOINT', 'https://sms.nalosolutions.com/smsbackend/Resl_Nalo/send-message/'),
            'auth_key' => env('NALO_AUTH_KEY'),
            'sender_id' => env('NALO_SENDER_ID', 'NALO'),
            'connect_timeout' => (int) env('NALO_CONNECT_TIMEOUT', 5),
            'timeout' => (int) env('NALO_TIMEOUT', 10),
        ],
    ],
    'assessment' => [
        'driver' => env('MEDIPATH_ASSESSMENT_DRIVER', 'deterministic'),
        'max_questions' => (int) env('MEDIPATH_ASSESSMENT_MAX_QUESTIONS', 10),
        'initial_ai_safety_screen' => (bool) env('MEDIPATH_INITIAL_AI_SAFETY_SCREEN', false),
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_ASSESSMENT_MODEL', 'gemini-3.5-flash-lite'),
            'consultant_model' => env('GEMINI_CONSULTANT_MODEL', 'gemini-flash-lite-latest'),
            'consultant_fallback_model' => env('GEMINI_CONSULTANT_FALLBACK_MODEL', 'gemini-2.5-flash-lite'),
            'consultant_endpoint' => env(
                'GEMINI_CONSULTANT_ENDPOINT',
                'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
            ),
            'endpoint' => env('GEMINI_API_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/interactions'),
            'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 5),
            'timeout' => (int) env('GEMINI_TIMEOUT', 20),
            'retry_delay_ms' => (int) env('GEMINI_RETRY_DELAY_MS', 1000),
            'consultant_timeout' => (int) env('GEMINI_CONSULTANT_TIMEOUT', 30),
            'consultant_initial_timeout' => (int) env('GEMINI_CONSULTANT_INITIAL_TIMEOUT', 12),
            'consultant_stream_initial_timeout' => (int) env('GEMINI_CONSULTANT_STREAM_INITIAL_TIMEOUT', 12),
            'consultant_stream_max_attempts' => (int) env('GEMINI_CONSULTANT_STREAM_MAX_ATTEMPTS', 1),
            'consultant_max_attempts' => (int) env('GEMINI_CONSULTANT_MAX_ATTEMPTS', 2),
            'consultant_max_output_tokens' => (int) env('GEMINI_CONSULTANT_MAX_OUTPUT_TOKENS', 320),
            'consultant_thinking_level' => env('GEMINI_CONSULTANT_THINKING_LEVEL', 'minimal'),
            'circuit_failure_threshold' => (int) env('GEMINI_CIRCUIT_FAILURE_THRESHOLD', 3),
            'circuit_failure_window_seconds' => (int) env('GEMINI_CIRCUIT_FAILURE_WINDOW_SECONDS', 120),
            'circuit_cooldown_seconds' => (int) env('GEMINI_CIRCUIT_COOLDOWN_SECONDS', 60),
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
