<?php

use App\Services\Pixelforge\Pricing;

test('it parses the Pixelforge pricing payload', function () {
    $pricing = Pricing::fromArray(pixelforgePricingPayload());

    expect($pricing->rates['output_tokens'])->toBe('40.00')
        ->and($pricing->charactersPerTextToken)->toBe(4)
        ->and($pricing->textTokenVariance)->toBe(0.15)
        ->and($pricing->perReferenceImage)->toBe(765)
        ->and($pricing->outputTokensFor('medium', '1024x1024'))->toBe(1100)
        ->and($pricing->reasoningRangeFor('high'))->toBe([1000, 8000]);
});

test('it survives a round trip through the cached array shape', function () {
    $pricing = Pricing::fromArray(Pricing::fromArray(pixelforgePricingPayload())->toArray());

    expect($pricing->reasoningRangeFor('medium'))->toBe([200, 2000])
        ->and($pricing->outputTokensFor('low', '1536x1024'))->toBe(420)
        ->and($pricing->toArray()['tokens']['reasoning']['low'])->toBe(['min' => 0, 'max' => 500]);
});

test('it refuses pricing without reasoning limits', function () {
    $payload = pixelforgePricingPayload();
    unset($payload['tokens']['reasoning']['medium']);

    Pricing::fromArray($payload);
})->throws(InvalidArgumentException::class);

test('it refuses unknown sizes', function () {
    Pricing::fromArray(pixelforgePricingPayload())->outputTokensFor('medium', '800x600');
})->throws(InvalidArgumentException::class);
