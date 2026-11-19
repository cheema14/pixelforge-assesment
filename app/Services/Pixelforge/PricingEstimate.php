<?php

namespace App\Services\Pixelforge;

final readonly class PricingEstimate
{
    public function __construct(
        public int $textTokens,
        public int $maxTextTokens,
        public int $imageTokens,
        public int $outputTokens,
        public int $reasoningTokens,
        public int $maxReasoningTokens,
        public int $providerCostNano,
        public int $chargeNano,
        public int $maxProviderCostNano,
        public int $maxChargeNano,
    ) {}

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'textTokens' => $this->textTokens,
            'maxTextTokens' => $this->maxTextTokens,
            'imageTokens' => $this->imageTokens,
            'outputTokens' => $this->outputTokens,
            'reasoningTokens' => $this->reasoningTokens,
            'maxReasoningTokens' => $this->maxReasoningTokens,
            'providerCostNano' => $this->providerCostNano,
            'chargeNano' => $this->chargeNano,
            'maxProviderCostNano' => $this->maxProviderCostNano,
            'maxChargeNano' => $this->maxChargeNano,
        ];
    }
}
