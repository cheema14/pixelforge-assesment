<?php

use App\Enums\CreditTransactionType;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\CreditLedger;

beforeEach(function () {
    config(['pixelforge.webhook_secret' => 'whsec_test_secret']);
});

/**
 * @return array{0: User, 1: Generation}
 */
function webhookGeneration(): array
{
    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 1_000_000_000, 'Test credit');

    $generation = Generation::factory()->for($user)->processing()->create([
        'pixelforge_id' => 'gen_hook',
        'estimated_charge_nano' => 72_187_500,
    ]);

    app(CreditLedger::class)->reserveForGeneration($user, $generation, 72_187_500);

    return [$user, $generation];
}

function sendWebhook($test, string $eventId, string $type, array $object)
{
    $body = pixelforgeWebhookBody($eventId, $type, $object);

    return $test->call('POST', '/webhooks/pixelforge', [], [], [], signedWebhookHeaders($body), $body);
}

test('a succeeded webhook settles the generation', function () {
    [$user, $generation] = webhookGeneration();

    $object = pixelforgeSnapshot('gen_hook', 'succeeded', [
        ...billableUsage(),
        'metadata' => ['app_generation_id' => $generation->id],
        'image_url' => 'https://cdn.pixelforge.test/image.png',
        'completed_at' => now()->toIso8601String(),
    ]);

    sendWebhook($this, 'evt_1', 'generation.succeeded', $object)->assertNoContent();

    $generation->refresh();

    expect($generation->status->value)->toBe('succeeded')
        ->and($generation->charged_nano)->toBe(65_625_000)
        ->and($generation->provider_cost_nano)->toBe(52_500_000)
        ->and($generation->settled_at)->not->toBeNull()
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000 - 65_625_000);

    $event = WebhookEvent::query()->sole();

    expect($event->event_id)->toBe('evt_1')
        ->and($event->generation_id)->toBe($generation->id)
        ->and($event->processed_at)->not->toBeNull();
});

test('a duplicate delivery is recorded and charged only once', function () {
    [$user, $generation] = webhookGeneration();

    $object = pixelforgeSnapshot('gen_hook', 'succeeded', [
        ...billableUsage(),
        'completed_at' => now()->toIso8601String(),
    ]);

    sendWebhook($this, 'evt_dupe', 'generation.succeeded', $object)->assertNoContent();
    sendWebhook($this, 'evt_dupe', 'generation.succeeded', $object)->assertNoContent();

    expect(WebhookEvent::query()->count())->toBe(1)
        ->and($generation->creditTransactions()->count())->toBe(2)
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000 - 65_625_000);
});

test('an out of order event cannot move a settled generation backwards', function () {
    [$user, $generation] = webhookGeneration();

    sendWebhook($this, 'evt_success', 'generation.succeeded', pixelforgeSnapshot('gen_hook', 'succeeded', [
        ...billableUsage(),
        'completed_at' => now()->toIso8601String(),
    ]))->assertNoContent();

    sendWebhook($this, 'evt_processing', 'generation.processing', pixelforgeSnapshot('gen_hook', 'processing', [
        'started_at' => now()->toIso8601String(),
    ]))->assertNoContent();

    $generation->refresh();

    expect($generation->status->value)->toBe('succeeded')
        ->and($generation->charged_nano)->toBe(65_625_000)
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000 - 65_625_000)
        ->and(WebhookEvent::query()->count())->toBe(2);
});

test('a late success cannot overwrite a recorded failure', function () {
    [$user, $generation] = webhookGeneration();

    sendWebhook($this, 'evt_failed', 'generation.failed', pixelforgeSnapshot('gen_hook', 'failed', [
        'error' => ['code' => 'content_policy_violation', 'message' => 'Rejected.'],
        'completed_at' => now()->toIso8601String(),
    ]))->assertNoContent();

    sendWebhook($this, 'evt_late_success', 'generation.succeeded', pixelforgeSnapshot('gen_hook', 'succeeded', [
        ...billableUsage(),
        'metadata' => ['app_generation_id' => $generation->id],
        'completed_at' => now()->toIso8601String(),
    ]))->assertNoContent();

    $generation->refresh();

    expect($generation->status->value)->toBe('failed')
        ->and($generation->charged_nano)->toBe(0)
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000);

    expect($generation->creditTransactions()
        ->where('type', CreditTransactionType::GenerationSettlement->value)
        ->count())->toBe(0);
});

test('it matches the generation by provider id when metadata is absent', function () {
    [, $generation] = webhookGeneration();

    sendWebhook($this, 'evt_meta', 'generation.succeeded', pixelforgeSnapshot('gen_hook', 'succeeded', [
        ...billableUsage(),
        'completed_at' => now()->toIso8601String(),
    ]))->assertNoContent();

    expect($generation->fresh()->status->value)->toBe('succeeded');
});

test('an event for an unknown generation is acknowledged and marked processed', function () {
    sendWebhook($this, 'evt_unknown', 'generation.succeeded', pixelforgeSnapshot('gen_missing', 'succeeded', [
        ...billableUsage(),
    ]))->assertNoContent();

    $event = WebhookEvent::query()->sole();

    expect($event->generation_id)->toBeNull()
        ->and($event->processed_at)->not->toBeNull();
});

test('non generation events are acknowledged without being recorded', function () {
    $body = pixelforgeWebhookBody('evt_other', 'account.updated', ['id' => 'acct_1']);

    $this->call('POST', '/webhooks/pixelforge', [], [], [], signedWebhookHeaders($body), $body)
        ->assertNoContent();

    expect(WebhookEvent::query()->count())->toBe(0);
});
