<?php

use App\Services\Pixelforge\PricingService;
use Illuminate\Support\Facades\Http;

test('it estimates the midpoint cost for a generation', function () {
    fakePixelforge();

    $estimate = app(PricingService::class)->estimate('', '1024x1024', 'medium');

    expect($estimate->textTokens)->toBe(0)
        ->and($estimate->outputTokens)->toBe(1100)
        ->and($estimate->reasoningTokens)->toBe(1100)
        ->and($estimate->providerCostNano)->toBe(57_750_000)
        ->and($estimate->chargeNano)->toBe(72_187_500)
        ->and($estimate->maxReasoningTokens)->toBe(2000)
        ->and($estimate->maxProviderCostNano)->toBe(69_000_000)
        ->and($estimate->maxChargeNano)->toBe(86_250_000);
});

test('it prices the prompt and each reference image', function () {
    fakePixelforge();

    $estimate = app(PricingService::class)->estimate(
        'A lighthouse at dusk',
        '1024x1024',
        'medium',
        ['https://example.com/a.png', 'https://example.com/b.png'],
    );

    expect($estimate->textTokens)->toBe(5)
        ->and($estimate->imageTokens)->toBe(1530)
        ->and($estimate->providerCostNano)->toBe(73_075_000)
        ->and($estimate->chargeNano)->toBe(91_343_750);
});

test('it uses the worst case reasoning load for the ceiling', function () {
    fakePixelforge();

    $estimate = app(PricingService::class)->estimate('', '1024x1024', 'high');

    expect($estimate->reasoningTokens)->toBe(4500)
        ->and($estimate->maxReasoningTokens)->toBe(8000)
        ->and($estimate->maxProviderCostNano)->toBeGreaterThan($estimate->providerCostNano);
});

test('it caches pricing so the estimate does not hit the network twice', function () {
    fakePixelforge();

    $pricing = app(PricingService::class);

    $pricing->pricing();
    $pricing->pricing();

    Http::assertSentCount(1);
});

test('it falls back to bundled pricing when the pricing endpoint fails', function () {
    config(['pixelforge.base_url' => pixelforgeBaseUrl()]);

    Http::fake([
        pixelforgeBaseUrl().'/pricing' => Http::response(['error' => 'boom'], 503),
    ]);

    $estimate = app(PricingService::class)->estimate('', '1024x1024', 'medium');

    expect($estimate->chargeNano)->toBe(72_187_500);
});
