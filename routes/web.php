<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Webhooks\PixelforgeWebhookController;
use App\Http\Middleware\VerifyPixelforgeSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if ($request->user()) {
        return to_route('generations.index');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

Route::get('/dashboard', fn () => to_route('generations.index'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::get('/generations/create', [GenerationController::class, 'create'])->name('generations.create');
    Route::post('/generations', [GenerationController::class, 'store'])
        ->middleware('throttle:generations')
        ->name('generations.store');

    Route::get('/credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('/credits', [CreditController::class, 'store'])
        ->middleware('throttle:credits')
        ->name('credits.store');

    Route::get('/admin/reports', [ReportController::class, 'index'])
        ->middleware('can:view-admin-reports')
        ->name('admin.reports.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::post('webhooks/pixelforge', PixelforgeWebhookController::class)
    ->middleware(VerifyPixelforgeSignature::class)
    ->name('webhooks.pixelforge');

require __DIR__.'/auth.php';
