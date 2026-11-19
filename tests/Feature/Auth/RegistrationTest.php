<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('generations.index', absolute: false));

    $user = User::query()->where('email', 'test@example.com')->sole();

    expect($user->creditTransactions()->sum('amount_nano'))->toBe(5_000_000_000);
});
