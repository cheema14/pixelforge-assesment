<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class PixelforgeGeneration
{
    /**
     * @param  list<string>  $referenceImages
     * @param  array<string, string>  $metadata
     * @param  array<string, int>|null  $usage
     * @param  array<string, string>  $rates
     */
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        public string $prompt,
        public string $size,
        public string $quality,
        public array $referenceImages,
        public array $metadata,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public ?array $usage,
        public array $rates,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? null;
        $status = GenerationStatus::tryFrom((string) ($data['status'] ?? ''));

        if (! is_string($id) || $id === '' || $status === null) {
            throw new InvalidArgumentException('Pixelforge returned an invalid generation payload.');
        }

        $usage = $data['usage'] ?? null;
        $rates = $data['rates'] ?? [];

        return new self(
            id: $id,
            status: $status,
            prompt: (string) ($data['prompt'] ?? ''),
            size: (string) ($data['size'] ?? ''),
            quality: (string) ($data['quality'] ?? ''),
            referenceImages: array_values(array_map('strval', (array) ($data['reference_images'] ?? []))),
            metadata: (array) ($data['metadata'] ?? []),
            imageUrl: isset($data['image_url']) ? (string) $data['image_url'] : null,
            imageUrlExpiresAt: self::date($data['image_url_expires_at'] ?? null),
            usage: is_array($usage) ? array_map('intval', $usage) : null,
            rates: is_array($rates) ? array_map(fn (mixed $rate): string => (string) $rate, $rates) : [],
            errorCode: isset($data['error']['code']) ? (string) $data['error']['code'] : null,
            errorMessage: isset($data['error']['message']) ? (string) $data['error']['message'] : null,
            createdAt: self::date($data['created_at'] ?? null),
            startedAt: self::date($data['started_at'] ?? null),
            completedAt: self::date($data['completed_at'] ?? null),
        );
    }

    public function hasBillableUsage(): bool
    {
        return $this->usage !== null && $this->rates !== [];
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
