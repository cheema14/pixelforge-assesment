<?php

namespace App\Services\Pixelforge;

use InvalidArgumentException;

final readonly class Pricing
{
    private const REQUIRED_RATES = [
        'input_text_tokens',
        'input_image_tokens',
        'output_tokens',
        'reasoning_tokens',
    ];

    /**
     * @param  array<string, string>  $rates
     * @param  array<string, array<string, int>>  $outputTokens
     * @param  array<string, int>  $reasoningMin
     * @param  array<string, int>  $reasoningMax
     */
    public function __construct(
        public array $rates,
        public int $charactersPerTextToken,
        public float $textTokenVariance,
        public int $perReferenceImage,
        public array $outputTokens,
        public array $reasoningMin,
        public array $reasoningMax,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rates = $data['rates'] ?? null;

        if (! is_array($rates)) {
            throw new InvalidArgumentException('Pricing payload is missing rates.');
        }

        foreach (self::REQUIRED_RATES as $rate) {
            if (! isset($rates[$rate])) {
                throw new InvalidArgumentException("Pricing payload is missing the [{$rate}] rate.");
            }
        }

        $tokens = $data['tokens'] ?? [];
        $reasoning = $tokens['reasoning'] ?? [];

        $outputTokens = [];

        foreach (($tokens['output'] ?? []) as $quality => $sizes) {
            if (! is_array($sizes)) {
                throw new InvalidArgumentException('Pricing payload has an invalid output token table.');
            }

            foreach ($sizes as $size => $count) {
                $outputTokens[$quality][$size] = (int) $count;
            }
        }

        $reasoningMin = [];
        $reasoningMax = [];

        foreach (['low', 'medium', 'high'] as $quality) {
            if (! isset($reasoning[$quality]['min'], $reasoning[$quality]['max'])) {
                throw new InvalidArgumentException("Pricing payload is missing reasoning limits for [{$quality}].");
            }

            $reasoningMin[$quality] = (int) $reasoning[$quality]['min'];
            $reasoningMax[$quality] = (int) $reasoning[$quality]['max'];
        }

        return new self(
            rates: array_map(fn (mixed $rate): string => (string) $rate, $rates),
            charactersPerTextToken: max(1, (int) ($tokens['characters_per_text_token'] ?? 4)),
            textTokenVariance: max(0.0, (float) ($tokens['text_token_variance'] ?? 0)),
            perReferenceImage: max(0, (int) ($tokens['per_reference_image'] ?? 0)),
            outputTokens: $outputTokens,
            reasoningMin: $reasoningMin,
            reasoningMax: $reasoningMax,
        );
    }

    public function outputTokensFor(string $quality, string $size): int
    {
        $tokens = $this->outputTokens[$quality][$size] ?? null;

        if ($tokens === null) {
            throw new InvalidArgumentException("No output token count for [{$quality}] at [{$size}].");
        }

        return $tokens;
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function reasoningRangeFor(string $quality): array
    {
        return [
            $this->reasoningMin[$quality],
            $this->reasoningMax[$quality],
        ];
    }

    /**
     * Shape shared with the Vue cost estimator.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rates' => $this->rates,
            'tokens' => [
                'characters_per_text_token' => $this->charactersPerTextToken,
                'text_token_variance' => $this->textTokenVariance,
                'per_reference_image' => $this->perReferenceImage,
                'output' => $this->outputTokens,
                'reasoning' => collect(['low', 'medium', 'high'])
                    ->mapWithKeys(fn (string $quality): array => [
                        $quality => [
                            'min' => $this->reasoningMin[$quality],
                            'max' => $this->reasoningMax[$quality],
                        ],
                    ])
                    ->all(),
            ],
        ];
    }
}
