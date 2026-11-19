<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

final class PixelforgeException extends RuntimeException
{
    private const NON_RETRYABLE_CODES = [
        'invalid_api_key',
        'validation_failed',
        'content_policy_violation',
        'not_found',
        'idempotency_key_reused',
        'daily_limit_exceeded',
        'date_range_too_long',
    ];

    public function __construct(
        string $message,
        public readonly ?string $providerCode = null,
        public readonly ?int $status = null,
        public readonly bool $retryable = false,
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $code = $response->json('error.code');
        $message = $response->json('error.message');

        return new self(
            message: is_string($message) && $message !== ''
                ? $message
                : "Pixelforge returned HTTP {$response->status()}.",
            providerCode: is_string($code) ? $code : null,
            status: $response->status(),
            retryable: self::isRetryable($response->status(), is_string($code) ? $code : null),
            retryAfterSeconds: self::retryAfter($response),
        );
    }

    public static function connectionFailed(ConnectionException $exception): self
    {
        return new self(
            message: 'Could not reach Pixelforge. It may be temporarily unavailable.',
            retryable: true,
            previous: $exception,
        );
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    private static function isRetryable(int $status, ?string $code): bool
    {
        if ($code !== null && in_array($code, self::NON_RETRYABLE_CODES, true)) {
            return false;
        }

        return $status === 429 || $status >= 500;
    }

    private static function retryAfter(Response $response): ?int
    {
        $seconds = (int) $response->header('Retry-After');

        return $seconds > 0 ? $seconds : null;
    }
}
