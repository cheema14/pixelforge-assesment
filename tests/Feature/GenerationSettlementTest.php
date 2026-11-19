<?php

use App\Actions\Generations\ApplyProviderSnapshot;
use App\Enums\CreditTransactionType;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditLedger;
use App\Services\Pixelforge\PixelforgeGeneration;

function settledGeneration(User $user, int $estimatedChargeNano = 72_187_500): Generation
{
    return Generation::factory()->for($user)->create([
        'status' => 'queued',
        'pixelforge_id' => 'gen_settle',
        'estimated_charge_nano' => $estimatedChargeNano,
        'max_charge_nano' => 86_250_000,
    ]);
}

test('a successful snapshot settles the reservation at the real cost', function () {
    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000, 'Test credit');

    $generation = settledGeneration($user);

    app(CreditLedger::class)->reserveForGeneration($user, $generation, 72_187_500);

    $snapshot = PixelforgeGeneration::fromArray(pixelforgeSnapshot('gen_settle', 'succeeded', [
        ...billableUsage(),
        'image_url' => 'https://cdn.pixelforge.test/image.png',
        'image_url_expires_at' => now()->addDay()->toIso8601String(),
        'started_at' => now()->subMinute()->toIso8601String(),
        'completed_at' => now()->toIso8601String(),
    ]));

    app(ApplyProviderSnapshot::class)->handle($generation, $snapshot);

    $generation->refresh();

    expect($generation->status->value)->toBe('succeeded')
        ->and($generation->provider_cost_nano)->toBe(52_500_000)
        ->and($generation->charged_nano)->toBe(65_625_000)
        ->and($generation->image_url)->toBe('https://cdn.pixelforge.test/image.png')
        ->and($generation->settled_at)->not->toBeNull();

    // The reservation was slightly higher than the real cost, so part is returned.
    expect(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000 - 65_625_000);

    $this->assertDatabaseHas('credit_transactions', [
        'generation_id' => $generation->id,
        'type' => CreditTransactionType::GenerationSettlement->value,
        'amount_nano' => 6_562_500,
    ]);
});

test('a failed snapshot refunds the whole reservation', function () {
    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000, 'Test credit');

    $generation = settledGeneration($user);
    app(CreditLedger::class)->reserveForGeneration($user, $generation, 72_187_500);

    $snapshot = PixelforgeGeneration::fromArray(pixelforgeSnapshot('gen_settle', 'failed', [
        'error' => ['code' => 'content_policy_violation', 'message' => 'The prompt was rejected.'],
        'completed_at' => now()->toIso8601String(),
    ]));

    app(ApplyProviderSnapshot::class)->handle($generation, $snapshot);

    $generation->refresh();

    expect($generation->status->value)->toBe('failed')
        ->and($generation->error_code)->toBe('content_policy_violation')
        ->and($generation->charged_nano)->toBe(0)
        ->and($generation->provider_cost_nano)->toBe(0)
        ->and($generation->settled_at)->not->toBeNull()
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000);

    $this->assertDatabaseHas('credit_transactions', [
        'generation_id' => $generation->id,
        'type' => CreditTransactionType::GenerationRefund->value,
        'amount_nano' => 72_187_500,
    ]);
});

test('out of order snapshots never move a settled generation backwards', function () {
    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000, 'Test credit');

    $generation = settledGeneration($user);
    app(CreditLedger::class)->reserveForGeneration($user, $generation, 72_187_500);

    $apply = app(ApplyProviderSnapshot::class);

    $apply->handle($generation, PixelforgeGeneration::fromArray(pixelforgeSnapshot('gen_settle', 'succeeded', [
        ...billableUsage(),
        'completed_at' => now()->toIso8601String(),
    ])));

    $apply->handle($generation, PixelforgeGeneration::fromArray(
        pixelforgeSnapshot('gen_settle', 'processing', ['started_at' => now()->toIso8601String()]),
    ));

    $generation->refresh();

    expect($generation->status->value)->toBe('succeeded')
        ->and($generation->charged_nano)->toBe(65_625_000)
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000 - 65_625_000);

    expect($generation->creditTransactions()->count())->toBe(2);
});

test('it ignores a snapshot for a different provider id', function () {
    $user = User::factory()->create();
    $generation = settledGeneration($user);

    app(ApplyProviderSnapshot::class)->handle(
        $generation,
        PixelforgeGeneration::fromArray(pixelforgeSnapshot('gen_other', 'succeeded', billableUsage())),
    );

    $generation->refresh();

    expect($generation->pixelforge_id)->toBe('gen_settle')
        ->and($generation->status->value)->toBe('queued')
        ->and($generation->settled_at)->toBeNull();
});

test('it records the provider id on a fresh submission', function () {
    $user = User::factory()->create();
    $generation = Generation::factory()->for($user)->create(['pixelforge_id' => null]);

    app(ApplyProviderSnapshot::class)->handle(
        $generation,
        PixelforgeGeneration::fromArray(pixelforgeSnapshot('gen_new', 'processing')),
    );

    $generation->refresh();

    expect($generation->pixelforge_id)->toBe('gen_new')
        ->and($generation->status->value)->toBe('processing')
        ->and($generation->submitted_at)->not->toBeNull();
});

test('a local failure refunds and is idempotent', function () {
    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000, 'Test credit');

    $generation = settledGeneration($user);
    app(CreditLedger::class)->reserveForGeneration($user, $generation, 72_187_500);

    $apply = app(ApplyProviderSnapshot::class);

    $apply->fail($generation, 'processing_timeout', 'The generation did not finish in time.');
    $apply->fail($generation, 'processing_timeout', 'The generation did not finish in time.');

    $generation->refresh();

    expect($generation->status->value)->toBe('failed')
        ->and($generation->error_code)->toBe('processing_timeout')
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000);

    expect($generation->creditTransactions()
        ->where('type', CreditTransactionType::GenerationRefund->value)
        ->count())->toBe(1);
});
