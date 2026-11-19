<?php

use App\Actions\Generations\ApplyProviderSnapshot;
use App\Jobs\ReconcileGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditLedger;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function runReconcile(Generation $generation): void
{
    (new ReconcileGeneration($generation))->handle(
        app(PixelforgeClient::class),
        app(ApplyProviderSnapshot::class),
    );
}

function reconcilableGeneration(array $attributes = []): Generation
{
    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000, 'Test credit');

    $generation = Generation::factory()->for($user)->processing()->create([
        'pixelforge_id' => 'gen_poll',
        'estimated_charge_nano' => 72_187_500,
        ...$attributes,
    ]);

    app(CreditLedger::class)->reserveForGeneration($user, $generation, 72_187_500);

    return $generation;
}

test('polling settles a generation even when the webhook never arrives', function () {
    Queue::fake();

    fakePixelforge([
        pixelforgeBaseUrl().'/generations/gen_poll' => Http::response(
            pixelforgeSnapshot('gen_poll', 'succeeded', [
                ...billableUsage(),
                'completed_at' => now()->toIso8601String(),
            ]),
        ),
    ]);

    $generation = reconcilableGeneration();

    runReconcile($generation);

    $generation->refresh();

    expect($generation->status->value)->toBe('succeeded')
        ->and($generation->charged_nano)->toBe(65_625_000)
        ->and($generation->settled_at)->not->toBeNull();

    Queue::assertNothingPushed();
});

test('it keeps polling while the provider is still working', function () {
    Queue::fake();

    fakePixelforge([
        pixelforgeBaseUrl().'/generations/gen_poll' => Http::response(
            pixelforgeSnapshot('gen_poll', 'processing', ['started_at' => now()->toIso8601String()]),
        ),
    ]);

    $generation = reconcilableGeneration();

    runReconcile($generation);

    expect($generation->fresh()->status->value)->toBe('processing');

    Queue::assertPushed(ReconcileGeneration::class, fn (ReconcileGeneration $job) => $job->generation->is($generation));
});

test('a generation that never finishes is failed and refunded', function () {
    Queue::fake();

    fakePixelforge([
        pixelforgeBaseUrl().'/generations/gen_poll' => Http::response(
            pixelforgeSnapshot('gen_poll', 'processing'),
        ),
    ]);

    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000, 'Test credit');

    $generation = Generation::factory()->for($user)->processing()->create([
        'pixelforge_id' => 'gen_poll',
        'estimated_charge_nano' => 72_187_500,
        'created_at' => now()->subMinutes(20),
    ]);

    app(CreditLedger::class)->reserveForGeneration($user, $generation, 72_187_500);

    runReconcile($generation);

    $generation->refresh();

    expect($generation->status->value)->toBe('failed')
        ->and($generation->error_code)->toBe('processing_timeout')
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000);

    Queue::assertNotPushed(ReconcileGeneration::class);
});

test('a generation the provider no longer knows is failed and refunded', function () {
    Queue::fake();

    fakePixelforge([
        pixelforgeBaseUrl().'/generations/gen_poll' => Http::response([
            'error' => ['code' => 'not_found', 'message' => 'Generation not found.'],
        ], 404),
    ]);

    $generation = reconcilableGeneration();

    runReconcile($generation);

    $generation->refresh();

    expect($generation->status->value)->toBe('failed')
        ->and($generation->error_code)->toBe('generation_not_found');
});

test('a settled generation is not polled again', function () {
    Http::fake();

    $generation = Generation::factory()->succeeded()->create(['pixelforge_id' => 'gen_done']);

    runReconcile($generation);

    Http::assertNothingSent();
});

test('a provider outage leaves the generation open for the next poll', function () {
    Queue::fake();

    fakePixelforge([
        pixelforgeBaseUrl().'/generations/gen_poll' => Http::response(['error' => 'unavailable'], 503),
    ]);

    $generation = reconcilableGeneration();

    expect(fn () => runReconcile($generation))->toThrow(PixelforgeException::class);

    expect($generation->fresh()->status->value)->toBe('processing');
});
