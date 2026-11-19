<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function pixelforgeBaseUrl(): string
{
    return 'https://pixelforge.test/v1';
}

/**
 * @return array<string, mixed>
 */
function pixelforgePricingPayload(): array
{
    return [
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
    ];
}

/**
 * Point the client at a fake host and stub the pricing endpoint.
 *
 * @param  array<string, mixed>  $stubs
 */
function fakePixelforge(array $stubs = []): void
{
    config(['pixelforge.base_url' => pixelforgeBaseUrl()]);

    Http::fake([
        pixelforgeBaseUrl().'/pricing' => Http::response(pixelforgePricingPayload()),
        ...$stubs,
    ]);
}

/**
 * Build a provider generation object as Pixelforge returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pixelforgeSnapshot(string $id, string $status, array $overrides = []): array
{
    return array_replace([
        'id' => $id,
        'status' => $status,
        'prompt' => 'A lighthouse at dusk',
        'size' => '1024x1024',
        'quality' => 'medium',
        'reference_images' => [],
        'metadata' => [],
        'created_at' => now()->subMinutes(2)->toIso8601String(),
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $object
 */
function pixelforgeWebhookBody(string $eventId, string $type, array $object): string
{
    return json_encode([
        'id' => $eventId,
        'type' => $type,
        'created_at' => now()->toIso8601String(),
        'data' => ['object' => $object],
    ], JSON_THROW_ON_ERROR);
}

/**
 * @return array<string, string>
 */
function signedWebhookHeaders(string $body, ?int $timestamp = null, string $secret = 'whsec_test_secret'): array
{
    $timestamp ??= time();

    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$body}", $secret),
    ];
}

/**
 * Usage and rates for a succeeded generation worth 52,500,000 nano ($0.0525).
 *
 * @return array{usage: array<string, int>, rates: array<string, string>}
 */
function billableUsage(): array
{
    return [
        'usage' => [
            'input_text_tokens' => 100,
            'input_image_tokens' => 0,
            'output_tokens' => 1100,
            'reasoning_tokens' => 640,
        ],
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
    ];
}
