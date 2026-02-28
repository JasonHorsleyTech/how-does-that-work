<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\TurnController;
use App\Http\Middleware\RedirectToGameState;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('games/create', [GameController::class, 'create'])->name('games.create');
    Route::post('games', [GameController::class, 'store'])->name('games.store');
    Route::post('games/{code}/start-submission', [GameController::class, 'startSubmission'])->name('games.start-submission');
    Route::post('games/{code}/start-game', [GameController::class, 'startGame'])->name('games.start-game');

    Route::get('billing', [BillingController::class, 'index'])->name('billing');
    Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('billing/success', [BillingController::class, 'success'])->name('billing.success');
});

// Stripe webhook — no CSRF, no auth
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

// Game page routes — redirect to correct game state if player visits wrong page
Route::middleware([RedirectToGameState::class])->group(function () {
    Route::get('games/{code}/lobby', [GameController::class, 'lobby'])->name('games.lobby');
    Route::get('games/{code}/submit', [TopicController::class, 'show'])->name('games.submit');
    Route::get('games/{code}/play', [TurnController::class, 'show'])->name('games.play');
    Route::get('games/{code}/results/{turnId}', [TurnController::class, 'results'])->name('games.results');
    Route::get('games/{code}/round-complete', [TurnController::class, 'roundComplete'])->name('games.round-complete');
    Route::get('games/{code}/complete', [TurnController::class, 'complete'])->name('games.complete');
});

// Game polling/API endpoints — no redirect middleware
Route::get('games/{code}/players', [GameController::class, 'players'])->name('games.players');
Route::get('games/{code}/submission-status', [GameController::class, 'submissionStatus'])->name('games.submission-status');
Route::get('games/{code}/play-state', [TurnController::class, 'playState'])->name('games.play-state');
Route::get('api/games/{code}/state', [TurnController::class, 'gameState'])->name('games.state');

// Game action routes — POST endpoints
Route::post('games/{code}/topics', [TopicController::class, 'store'])->name('games.topics.store');
Route::post('games/{code}/turns/{turnId}/choose-topic', [TurnController::class, 'chooseTopic'])->name('games.turns.choose-topic');
Route::post('games/{code}/turns/{turnId}/start-recording', [TurnController::class, 'startRecording'])->name('games.turns.start-recording');
Route::post('api/games/{code}/turns/{turnId}/audio', [TurnController::class, 'storeAudio'])->name('games.turns.store-audio');
Route::post('api/games/{code}/turns/{turnId}/host-upload-audio', [TurnController::class, 'hostUploadAudio'])->name('games.turns.host-upload-audio');
Route::post('games/{code}/advance', [TurnController::class, 'advance'])->name('games.advance');
Route::post('games/{code}/start-next-round', [TurnController::class, 'startNextRound'])->name('games.start-next-round');
Route::post('games/{code}/finalize', [TurnController::class, 'finalizeGame'])->name('games.finalize');
Route::post('games/{code}/play-again', [TurnController::class, 'playAgain'])->name('games.play-again');

Route::get('join/{code}', [JoinController::class, 'show'])->name('games.join.show');
Route::post('join/{code}', [JoinController::class, 'store'])->name('games.join.store');
Route::post('join/{code}/reconnect', [JoinController::class, 'reconnect'])->name('games.join.reconnect');

require __DIR__.'/settings.php';
