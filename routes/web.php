<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\JoinController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('games/create', [GameController::class, 'create'])->name('games.create');
    Route::post('games', [GameController::class, 'store'])->name('games.store');
    Route::get('games/{code}/lobby', [GameController::class, 'lobby'])->name('games.lobby');
});

Route::get('join/{code}', [JoinController::class, 'show'])->name('games.join.show');
Route::post('join/{code}', [JoinController::class, 'store'])->name('games.join.store');

require __DIR__.'/settings.php';
