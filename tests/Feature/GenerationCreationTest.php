<?php

use App\Enums\CreditTransactionType;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditLedger;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->withoutVite();
    fakePixelforge();
});

function generationUser(int $balanceNano = 1_000_000_000): User
{
    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, $balanceNano, 'Test credit');

    return $user;
}

test('a generation reserves its estimate and queues submission', function () {
    Queue::fake();

    $user = generationUser();

    $response = $this->actingAs($user)->post('/generations', [
        'prompt' => 'x',
        'size' => '1024x1024',
        'quality' => 'medium',
    ]);

    $response->assertRedirect(route('generations.index'));

    $generation = Generation::query()->sole();

    expect($generation->user_id)->toBe($user->id)
        ->and($generation->status->value)->toBe('queued')
        ->and($generation->estimated_charge_nano)->toBe(72_193_750)
        ->and($generation->reference_images)->toBe([]);

    expect(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000_000 - 72_193_750);

    $this->assertDatabaseHas('credit_transactions', [
        'user_id' => $user->id,
        'generation_id' => $generation->id,
        'type' => CreditTransactionType::GenerationReserve->value,
        'amount_nano' => -72_193_750,
    ]);

    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generation->is($generation));
});

test('reference images are validated, stored and priced', function () {
    Queue::fake();

    $user = generationUser();

    $this->actingAs($user)->post('/generations', [
        'prompt' => 'A lighthouse at dusk',
        'size' => '1024x1024',
        'quality' => 'medium',
        'reference_images' => [
            'https://example.com/a.png',
            ' https://example.com/b.png ',
            '',
        ],
    ])->assertRedirect(route('generations.index'));

    $generation = Generation::query()->sole();

    expect($generation->reference_images)->toBe([
        'https://example.com/a.png',
        'https://example.com/b.png',
    ])
        ->and($generation->estimated_charge_nano)->toBe(91_343_750);
});

test('a generation is blocked when the balance cannot cover the estimate', function () {
    Queue::fake();

    $user = generationUser(1_000_000);

    $this->actingAs($user)
        ->post('/generations', [
            'prompt' => 'x',
            'size' => '1024x1024',
            'quality' => 'medium',
        ])
        ->assertSessionHasErrors('balance');

    expect(Generation::query()->count())->toBe(0)
        ->and(app(CreditLedger::class)->balanceNano($user))->toBe(1_000_000);

    Queue::assertNothingPushed();
});

test('it validates the generation payload', function (array $payload, string $field) {
    $user = generationUser();

    $this->actingAs($user)
        ->post('/generations', $payload)
        ->assertSessionHasErrors($field);

    expect(Generation::query()->count())->toBe(0);
})->with([
    'missing prompt' => [['size' => '1024x1024', 'quality' => 'low'], 'prompt'],
    'unknown size' => [['prompt' => 'x', 'size' => '800x600', 'quality' => 'low'], 'size'],
    'unknown quality' => [['prompt' => 'x', 'size' => '1024x1024', 'quality' => 'ultra'], 'quality'],
    'too many references' => [[
        'prompt' => 'x',
        'size' => '1024x1024',
        'quality' => 'low',
        'reference_images' => array_fill(0, 5, 'https://example.com/a.png'),
    ], 'reference_images'],
    'invalid reference url' => [[
        'prompt' => 'x',
        'size' => '1024x1024',
        'quality' => 'low',
        'reference_images' => ['ftp://example.com/a.png'],
    ], 'reference_images.0'],
]);

test('guests cannot create generations', function () {
    $this->get('/generations/create')->assertRedirect('/login');

    $this->post('/generations', [
        'prompt' => 'x',
        'size' => '1024x1024',
        'quality' => 'low',
    ])->assertRedirect('/login');
});
