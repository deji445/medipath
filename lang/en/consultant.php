<?php

return [
    'emergency_message' => 'This may be an emergency. Call :phone now. Do not wait for another chat reply.',
    'validation' => [
        'required' => 'Type a message for the AI consultant.',
        'max' => 'Keep the message under 1,000 characters.',
    ],
    'errors' => [
        'connection' => 'Gemini took too long to respond. Your message is still here, so you can try again.',
        'unavailable' => 'Gemini is temporarily busy. Please try again in a moment.',
        'rate_limited' => 'Gemini received too many requests in a short period. Please wait a minute and try again.',
        'models_unavailable' => 'The AI models are temporarily unavailable. Your message is still here, so please try again shortly.',
        'not_configured' => 'The AI consultant has not been configured yet. You can request a real consultant instead.',
        'circuit_open' => 'Gemini is temporarily unavailable after repeated failures. Please wait a minute or request a real consultant.',
        'default' => 'The AI consultant is unavailable right now. Try again, or request a real consultant.',
    ],
    'first_aid' => [
        'bleeding_pressure' => 'Use a clean cloth or dressing to apply firm, continuous pressure to the wound.',
        'bleeding_layers' => 'If blood soaks through, add another cloth or dressing on top. Keep the first one in place.',
        'embedded_object' => 'If something is stuck in the wound, leave it in place and press around it, not on it.',
        'fracture_still' => 'Keep the injured limb still. Do not straighten it or put weight on it.',
        'unsafe_area' => 'Do not move the person unless the area is unsafe.',
        'dispatcher' => 'Keep the person comfortable and follow the 112 dispatcher\'s instructions.',
    ],
];
