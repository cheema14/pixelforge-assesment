<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider connection
    |--------------------------------------------------------------------------
    |
    | Each environment should use its own Pixelforge key. Pixelforge scopes
    | webhooks and the usage report to the key, so sharing one key between
    | local development and production makes usage reconciliation noisy.
    |
    */

    'base_url' => env('PIXELFORGE_BASE_URL', 'https://pixelforge.promptedit.dev/v1'),
    'api_key' => env('PIXELFORGE_API_KEY'),
    'webhook_secret' => env('PIXELFORGE_WEBHOOK_SECRET'),
    'webhook_tolerance_seconds' => 300,

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | We charge Pixelforge's token cost plus this markup. The markup is stored
    | as a whole percentage so every calculation can stay in integer nanodollars.
    |
    */

    'markup_percent' => 25,
    'welcome_credit_nano' => 5_000_000_000,
    'pricing_cache_seconds' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Reconciliation
    |--------------------------------------------------------------------------
    |
    | Webhooks are the primary source of status updates. These settings control
    | the backup poller that repairs missed or failed webhook deliveries.
    |
    */

    'reconcile_interval_seconds' => 15,
    'max_processing_minutes' => 15,
    'stale_submission_minutes' => 2,
    'provider_usage_cache_seconds' => 300,

    /*
    |--------------------------------------------------------------------------
    | Fallback pricing
    |--------------------------------------------------------------------------
    |
    | Used only to estimate costs when GET /v1/pricing is temporarily
    | unreachable. Actual charges always use the rates returned with each
    | generation, so a stale fallback can never affect real billing.
    |
    */

    'fallback_pricing' => [
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ],

];
