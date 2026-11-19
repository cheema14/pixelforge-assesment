<?php

use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function fakeUsageReport(array $data, array $total): void
{
    config(['pixelforge.base_url' => pixelforgeBaseUrl()]);

    Http::fake([
        pixelforgeBaseUrl().'/usage*' => Http::response(['data' => $data, 'total' => $total]),
    ]);
}

function reportGeneration(User $user, string $completedAt, int $charged, int $providerCost): Generation
{
    return Generation::factory()->for($user)->succeeded()->create([
        'completed_at' => $completedAt,
        'settled_at' => $completedAt,
        'charged_nano' => $charged,
        'provider_cost_nano' => $providerCost,
    ]);
}

test('guests and non admins cannot view the report', function () {
    $this->get('/admin/reports')->assertRedirect('/login');

    $this->actingAs(User::factory()->create())
        ->get('/admin/reports')
        ->assertForbidden();
});

test('it reports credit spend, provider cost and profit by day and user', function () {
    $admin = User::factory()->admin()->create();
    $alice = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

    reportGeneration($alice, '2026-11-18 10:00:00', 65_625_000, 52_500_000);
    reportGeneration($alice, '2026-11-18 12:00:00', 10_000_000, 8_000_000);
    reportGeneration($bob, '2026-11-19 09:00:00', 25_000_000, 20_000_000);

    // A failed generation is not revenue and must not appear.
    Generation::factory()->for($alice)->failed()->create(['completed_at' => '2026-11-19 11:00:00']);

    fakeUsageReport(
        [
            ['date' => '2026-11-18', 'cost_usd' => '0.060500', 'generations' => 2],
            ['date' => '2026-11-19', 'cost_usd' => '0.020000', 'generations' => 1],
        ],
        ['cost_usd' => '0.080500', 'generations' => 3],
    );

    $response = $this->actingAs($admin)
        ->get('/admin/reports?from=2026-11-18&to=2026-11-19')
        ->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Report')
        ->where('filter.from', '2026-11-18')
        ->where('filter.to', '2026-11-19')
        ->where('report.totals.generations', 3)
        ->where('report.totals.chargedNano', 100_625_000)
        ->where('report.totals.providerCostNano', 80_500_000)
        ->where('report.totals.profitNano', 20_125_000)
        ->where('report.totals.providerUsageNano', 80_500_000)
        ->where('report.totals.differenceNano', 0)
        ->where('report.totals.reconciled', true)
        ->has('report.days', 2)
        ->where('report.days.0.date', '2026-11-18')
        ->where('report.days.0.generations', 2)
        ->where('report.days.0.chargedNano', 75_625_000)
        ->where('report.days.0.providerUsageNano', 60_500_000)
        ->where('report.days.0.differenceNano', 0)
        ->has('report.users', 2)
        ->where('report.users.0.name', 'Alice')
        ->where('report.users.0.generations', 2)
        ->where('report.users.0.chargedNano', 75_625_000)
        ->where('report.users.1.name', 'Bob')
        ->where('report.usageError', null));
});

test('it surfaces a usage difference instead of hiding it', function () {
    $admin = User::factory()->admin()->create();

    fakeUsageReport(
        [['date' => '2026-11-19', 'cost_usd' => '0.100000', 'generations' => 2]],
        ['cost_usd' => '0.100000', 'generations' => 2],
    );

    $this->actingAs($admin)
        ->get('/admin/reports?from=2026-11-19&to=2026-11-19')
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.totals.providerUsageNano', 100_000_000)
            ->where('report.totals.differenceNano', -100_000_000)
            ->where('report.totals.reconciled', false)
            ->where('report.days.0.reconciled', false));
});

test('it still renders when the usage endpoint is unavailable', function () {
    $admin = User::factory()->admin()->create();
    $alice = User::factory()->create();
    reportGeneration($alice, '2026-11-19 09:00:00', 25_000_000, 20_000_000);

    config(['pixelforge.base_url' => pixelforgeBaseUrl()]);
    Http::fake([
        pixelforgeBaseUrl().'/usage*' => Http::response(['error' => ['code' => 'provider_down']], 503),
    ]);

    $this->actingAs($admin)
        ->get('/admin/reports?from=2026-11-19&to=2026-11-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.totals.chargedNano', 25_000_000)
            ->where('report.totals.providerUsageNano', null)
            ->where('report.totals.reconciled', false)
            ->whereNot('report.usageError', null));
});

test('it rejects invalid date ranges', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/reports?from=2026-11-20&to=2026-11-19')
        ->assertSessionHasErrors('from');

    $this->actingAs($admin)
        ->get('/admin/reports?from=2026-01-01&to=2026-11-19')
        ->assertSessionHasErrors('to');
});
