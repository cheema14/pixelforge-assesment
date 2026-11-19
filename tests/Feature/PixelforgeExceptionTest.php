<?php

use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

function providerError(int $status, string $code, array $headers = []): PixelforgeException
{
    /** @var Response $response */
    $response = new Response(Http::psr7Response(
        ['error' => ['code' => $code, 'message' => 'Provider says no.']],
        $status,
        $headers,
    ));

    return PixelforgeException::fromResponse($response);
}

test('provider errors carry their code and message', function () {
    $exception = providerError(422, 'content_policy_violation');

    expect($exception->providerCode)->toBe('content_policy_violation')
        ->and($exception->status)->toBe(422)
        ->and($exception->getMessage())->toBe('Provider says no.');
});

test('it decides which provider errors are worth retrying', function (int $status, string $code, bool $retryable) {
    expect(providerError($status, $code)->retryable)->toBe($retryable);
})->with([
    'rate limit' => [429, 'rate_limit_exceeded', true],
    'concurrency limit' => [429, 'concurrency_limit_exceeded', true],
    'daily quota' => [429, 'daily_limit_exceeded', false],
    'overloaded' => [503, 'overloaded', true],
    'server error' => [500, 'internal_error', true],
    'validation' => [422, 'validation_failed', false],
    'content policy' => [422, 'content_policy_violation', false],
    'idempotency reuse' => [409, 'idempotency_key_reused', false],
    'not found' => [404, 'not_found', false],
    'bad key' => [401, 'invalid_api_key', false],
]);

test('it honours the Retry-After header', function () {
    expect(providerError(429, 'rate_limit_exceeded', ['Retry-After' => '7'])->retryAfterSeconds)->toBe(7)
        ->and(providerError(429, 'rate_limit_exceeded')->retryAfterSeconds)->toBeNull();
});

test('it recognises a missing generation', function () {
    expect(providerError(404, 'not_found')->isNotFound())->toBeTrue()
        ->and(providerError(422, 'content_policy_violation')->isNotFound())->toBeFalse();
});

test('a connection failure is retryable', function () {
    $exception = PixelforgeException::connectionFailed(new ConnectionException('Connection timed out.'));

    expect($exception->retryable)->toBeTrue()
        ->and($exception->status)->toBeNull();
});
