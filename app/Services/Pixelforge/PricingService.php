<?php

namespace App\Services\Pixelforge;

use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class PricingService
{
    public function __construct(private readonly PixelforgeClient $client) {}

    public function pricing(): Pricing
    {
        // Cache the plain payload rather than the value object so the cache
        // stays portable across drivers and avoids PHP unserialization rules.
        $payload = Cache::remember(
            'pixelforge.pricing',
            now()->addSeconds((int) config('pixelforge.pricing_cache_seconds')),
            function (): array {
                try {
                    return $this->client->pricing()->toArray();
                } catch (PixelforgeException $exception) {
                    Log::warning('Falling back to bundled Pixelforge pricing.', [
                        'message' => $exception->getMessage(),
                    ]);

                    return (array) config('pixelforge.fallback_pricing');
                }
            },
        );

        return Pricing::fromArray($payload);
    }

    /**
     * Estimate the provider cost and customer price before submission.
     *
     * Text and reasoning token counts are only known once a generation
     * completes, so the estimate uses the documented midpoint for reasoning.
     * The "max" values cover Pixelforge's documented worst case.
     *
     * @param  list<string>  $referenceImages
     */
    public function estimate(string $prompt, string $size, string $quality, array $referenceImages = []): PricingEstimate
    {
        $pricing = $this->pricing();
        $markupPercent = (int) config('pixelforge.markup_percent');

        $textTokens = (int) ceil(Str::length($prompt) / $pricing->charactersPerTextToken);
        $maxTextTokens = (int) ceil($textTokens * (1 + $pricing->textTokenVariance));
        $imageTokens = count($referenceImages) * $pricing->perReferenceImage;
        $outputTokens = $pricing->outputTokensFor($quality, $size);
        [$reasoningMin, $reasoningMax] = $pricing->reasoningRangeFor($quality);
        $reasoningTokens = intdiv($reasoningMin + $reasoningMax, 2);

        $providerCost = $this->tokenCost($pricing, $textTokens, $imageTokens, $outputTokens, $reasoningTokens);
        $maxProviderCost = $this->tokenCost($pricing, $maxTextTokens, $imageTokens, $outputTokens, $reasoningMax);

        return new PricingEstimate(
            textTokens: $textTokens,
            maxTextTokens: $maxTextTokens,
            imageTokens: $imageTokens,
            outputTokens: $outputTokens,
            reasoningTokens: $reasoningTokens,
            maxReasoningTokens: $reasoningMax,
            providerCostNano: $providerCost,
            chargeNano: Money::withMarkup($providerCost, $markupPercent),
            maxProviderCostNano: $maxProviderCost,
            maxChargeNano: Money::withMarkup($maxProviderCost, $markupPercent),
        );
    }

    private function tokenCost(
        Pricing $pricing,
        int $textTokens,
        int $imageTokens,
        int $outputTokens,
        int $reasoningTokens,
    ): int {
        return Money::costForTokens($textTokens, $pricing->rates['input_text_tokens'])
            + Money::costForTokens($imageTokens, $pricing->rates['input_image_tokens'])
            + Money::costForTokens($outputTokens, $pricing->rates['output_tokens'])
            + Money::costForTokens($reasoningTokens, $pricing->rates['reasoning_tokens']);
    }
}
