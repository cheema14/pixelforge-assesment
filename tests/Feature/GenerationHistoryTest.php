<?php

use App\Models\Generation;
use App\Models\User;
use App\Services\CreditLedger;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('the history page only lists the current user generations', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->succeeded()->create(['prompt' => 'Mine']);
    Generation::factory()->for(User::factory())->create(['prompt' => 'Someone else']);

    $this->actingAs($user)
        ->get('/generations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Generations/Index')
            ->has('generations.data', 1)
            ->where('generations.data.0.prompt', 'Mine')
            ->where('generations.data.0.status', 'succeeded')
            ->where('filter.status', null));
});

test('the history page can filter by status', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->create(['status' => 'queued']);
    Generation::factory()->for($user)->failed()->create();

    $this->actingAs($user)
        ->get('/generations?status=failed')
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations.data', 1)
            ->where('generations.data.0.status', 'failed')
            ->where('filter.status', 'failed'));
});

test('an unknown status filter is ignored', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->create();
    Generation::factory()->for($user)->failed()->create();

    $this->actingAs($user)
        ->get('/generations?status=bogus')
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations.data', 2)
            ->where('filter.status', null));
});

test('it flags an image whose provider url has expired', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->succeeded()->create([
        'image_url_expires_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get('/generations')
        ->assertInertia(fn (Assert $page) => $page
            ->where('generations.data.0.imageExpired', true));
});

test('the create page shares pricing and the current balance', function () {
    fakePixelforge();

    $user = User::factory()->create();
    app(CreditLedger::class)->purchase($user, 30_000_000_000, 'Test credit');

    $this->actingAs($user)
        ->get('/generations/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Generations/Create')
            ->where('pricing.tokens.reasoning.medium.min', 200)
            ->where('markupPercent', 25)
            ->where('balanceNano', 30_000_000_000));
});
