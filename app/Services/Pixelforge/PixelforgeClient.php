<?php

namespace App\Services\Pixelforge;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class PixelforgeClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function createGeneration(array $parameters, string $idempotencyKey): PixelforgeGeneration
    {
        $payload = $this->send(fn (PendingRequest $request) => $request
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('generations', $parameters));

        return PixelforgeGeneration::fromArray($payload);
    }

    public function generation(string $id): PixelforgeGeneration
    {
        $payload = $this->send(fn (PendingRequest $request) => $request->get('generations/'.rawurlencode($id)));

        return PixelforgeGeneration::fromArray($payload);
    }

    public function pricing(): Pricing
    {
        $payload = $this->send(fn (PendingRequest $request) => $request->get('pricing'), authenticated: false);

        return Pricing::fromArray($payload);
    }

    public function usage(CarbonImmutable $from, CarbonImmutable $to): ProviderUsageReport
    {
        $payload = $this->send(fn (PendingRequest $request) => $request->get('usage', [
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
        ]));

        return ProviderUsageReport::fromArray($payload);
    }

    /**
     * @param  Closure(PendingRequest): Response  $callback
     * @return array<string, mixed>
     */
    private function send(Closure $callback, bool $authenticated = true): array
    {
        if ($authenticated && $this->apiKey === '') {
            throw new PixelforgeException('The Pixelforge API key is not configured.');
        }

        try {
            $response = $callback($this->request());
        } catch (ConnectionException $exception) {
            throw PixelforgeException::connectionFailed($exception);
        }

        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new PixelforgeException('Pixelforge returned an unexpected response.', retryable: true);
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20);

        return $this->apiKey !== '' ? $request->withToken($this->apiKey) : $request;
    }
}
