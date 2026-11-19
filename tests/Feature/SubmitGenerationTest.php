<?php

use App\Actions\Generations\ApplyProviderSnapshot;
use App\Jobs\ReconcileGeneration;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditLedger;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function runSubmit(Generation $generation): void
{
    (new SubmitGeneration($generation))->handle(
        app(PixelforgeClient::class),
        app(ApplyProviderSnapshot::class),
    );
}

test('it submits the prompt with an idempotency key and records the provider id', function () {
    fakePixelforge([
        pixelforgeBaseUrl().'/generations' => Http::response(
            pixelforgeSnapshot('gen_new', 'processing', ['started_at' => now()->toIso8601String()]),
            202,
        ),
    ]);

    $user = User::factory()->create();
    $generation = Generation::factory()->for($user)->create([
        'pixelforge_id' => null,
        'prompt' => 'A lighthouse at dusk',
        'reference_images' => ['https://example.com/ref.png'],
    ]);

    runSubmit($generation);

    $generation->refresh();

    expect($generation->pixelforge_id)->toBe('gen_new')
        ->and($generation->status->value)->toBe('processing')
        ->and($generation->submitted_at)->not->toBeNull();

    Http::assertSent(function (Request $request) use ($generation): bool {
        return $request->url() === pixelforgeBaseUrl().'/generations'
            && $request->method() === 'POST'
            && $request['metadata']['app_generation_id'] === $generation->id
            && $request['reference_images'] === ['https://example.com/ref.png']
            && $request->header('Idempotency-Key')[0] === $generation->id;
    });

    Queue::assertPushed(ReconcileGeneration::class, fn (ReconcileGeneration $job) => $job->generation->is($generation));
});

test('a non retryable rejection fails the generation and refunds it', function () {
    fakePixelforge([
        pixelforgeBaseUrl().'/generations' => Http::response([
            'error' => ['code' => 'content_policy_violation', 'message' => 'That prompt is not allowed.'],
        ], 422),
    ]);

    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000, 'Test credit');

    $generation = Generation::factory()->for($user)->create([
        'pixelforge_id' => null,
        'estimated_charge_nano' => 72_187_500,
    ]);

    app(CreditLedger::class)->reserveForGeneration($user, $generation, 72_187_500);

    runSubmit($generation);

    $generation->refresh();

    expect($generation->status->value)->toBe('failed')
        ->and($generation->error_code)->toBe('content_policy_violation')
        ->and($generation->error_message)->toBe('That prompt is not allowed.')
        ->and($generation->settled_at)->not->toBeNull()
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000);

    Queue::assertNotPushed(ReconcileGeneration::class);
});

test('a used up daily quota fails the generation instead of retrying', function () {
    fakePixelforge([
        pixelforgeBaseUrl().'/generations' => Http::response([
            'error' => ['code' => 'daily_limit_exceeded', 'message' => 'The daily generation quota is used up.'],
        ], 429),
    ]);

    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000, 'Test credit');

    $generation = Generation::factory()->for($user)->create([
        'pixelforge_id' => null,
        'estimated_charge_nano' => 72_187_500,
    ]);

    app(CreditLedger::class)->reserveForGeneration($user, $generation, 72_187_500);

    runSubmit($generation);

    $generation->refresh();

    expect($generation->status->value)->toBe('failed')
        ->and($generation->error_code)->toBe('daily_limit_exceeded')
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000);

    Http::assertSentCount(1);
});

test('an already submitted generation is not submitted twice', function () {
    fakePixelforge();

    $generation = Generation::factory()->create();

    runSubmit($generation);

    Http::assertNothingSent();
    Queue::assertNothingPushed();
});
