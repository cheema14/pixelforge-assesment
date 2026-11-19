<?php

use App\Models\WebhookEvent;

beforeEach(function () {
    config(['pixelforge.webhook_secret' => 'whsec_test_secret']);
});

function postWebhook($test, string $body, array $headers)
{
    return $test->call('POST', '/webhooks/pixelforge', [], [], [], $headers, $body);
}

test('it accepts a correctly signed webhook', function () {
    $body = pixelforgeWebhookBody('evt_ping', 'ping', ['id' => 'gen_none']);

    postWebhook($this, $body, signedWebhookHeaders($body))->assertNoContent();
});

test('it rejects a webhook with no signature header', function () {
    $body = pixelforgeWebhookBody('evt_ping', 'ping', ['id' => 'gen_none']);

    postWebhook($this, $body, ['CONTENT_TYPE' => 'application/json'])->assertStatus(400);
});

test('it rejects a signature that does not match the body', function () {
    $headers = signedWebhookHeaders('{"tampered":true}');
    $body = pixelforgeWebhookBody('evt_ping', 'ping', ['id' => 'gen_none']);

    postWebhook($this, $body, $headers)->assertStatus(400);
});

test('it rejects a stale signature timestamp', function () {
    $body = pixelforgeWebhookBody('evt_ping', 'ping', ['id' => 'gen_none']);

    postWebhook($this, $body, signedWebhookHeaders($body, time() - 301))->assertStatus(400);
});

test('it rejects webhooks when no secret is configured', function () {
    config(['pixelforge.webhook_secret' => '']);

    $body = pixelforgeWebhookBody('evt_ping', 'ping', ['id' => 'gen_none']);

    postWebhook($this, $body, signedWebhookHeaders($body))->assertStatus(500);
});

test('a ping is acknowledged without being recorded', function () {
    $body = pixelforgeWebhookBody('evt_ping', 'ping', ['id' => 'gen_none']);

    postWebhook($this, $body, signedWebhookHeaders($body))->assertNoContent();

    expect(WebhookEvent::query()->count())->toBe(0);
});

test('it rejects a verified event with no generation object', function () {
    $body = json_encode(['id' => 'evt_bad', 'type' => 'generation.succeeded'], JSON_THROW_ON_ERROR);

    postWebhook($this, $body, signedWebhookHeaders($body))->assertStatus(400);
});
