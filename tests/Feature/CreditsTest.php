<?php

use App\Models\User;
use App\Services\CreditLedger;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('the credits page shows the ledger balance', function () {
    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 12_500_000_000, 'Credit purchase (demo)');

    $this->actingAs($user)
        ->get('/credits')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Credits/Index')
            ->where('balanceNano', 12_500_000_000)
            ->has('transactions.data', 1)
            ->where('transactions.data.0.amountNano', 12_500_000_000)
            ->where('markupPercent', 25));
});

test('a demo purchase credits the balance', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/credits')
        ->post('/credits', ['amount' => '12.50'])
        ->assertRedirect('/credits')
        ->assertSessionHas('success', '$12.50 was added to your balance.');

    expect(app(CreditLedger::class)->balanceNano($user))->toBe(12_500_000_000);
});

test('purchases must stay within the demo limits', function (string $amount) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/credits', ['amount' => $amount])
        ->assertSessionHasErrors('amount');

    expect(app(CreditLedger::class)->balanceNano($user))->toBe(0);
})->with(['0', '0.99', '501', '10.123', 'abc']);

test('guests cannot reach the credits page', function () {
    $this->get('/credits')->assertRedirect('/login');
    $this->post('/credits', ['amount' => '10'])->assertRedirect('/login');
});
