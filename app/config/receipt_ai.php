<?php

return [
    'api_key' => env('RECEIPT_AI_API_KEY', env('OPENAI_API_KEY')),
    'model' => env('RECEIPT_AI_MODEL', 'gpt-4.1-mini'),
];
