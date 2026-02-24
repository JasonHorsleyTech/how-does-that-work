<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\TurnController;
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
    Route::post('games/{code}/start-submission', [GameController::class, 'startSubmission'])->name('games.start-submission');
    Route::post('games/{code}/start-game', [GameController::class, 'startGame'])->name('games.start-game');
});

// Lobby accessible to authenticated hosts and guest players (session-based)
Route::get('games/{code}/lobby', [GameController::class, 'lobby'])->name('games.lobby');
Route::get('games/{code}/players', [GameController::class, 'players'])->name('games.players');

// Submit phase: accessible to hosts and guest players (session-based)
Route::get('games/{code}/submit', [TopicController::class, 'show'])->name('games.submit');
Route::post('games/{code}/topics', [TopicController::class, 'store'])->name('games.topics.store');
Route::get('games/{code}/submission-status', [GameController::class, 'submissionStatus'])->name('games.submission-status');

// Play phase: accessible to hosts and guest players (session-based)
Route::get('games/{code}/play', [TurnController::class, 'show'])->name('games.play');
Route::post('games/{code}/turns/{turnId}/choose-topic', [TurnController::class, 'chooseTopic'])->name('games.turns.choose-topic');
Route::get('games/{code}/play-state', [TurnController::class, 'playState'])->name('games.play-state');
Route::post('games/{code}/turns/{turnId}/start-recording', [TurnController::class, 'startRecording'])->name('games.turns.start-recording');
Route::post('api/games/{code}/turns/{turnId}/audio', [TurnController::class, 'storeAudio'])->name('games.turns.store-audio');
Route::get('games/{code}/results/{turnId}', [TurnController::class, 'results'])->name('games.results');
Route::post('games/{code}/advance', [TurnController::class, 'advance'])->name('games.advance');

Route::get('join/{code}', [JoinController::class, 'show'])->name('games.join.show');
Route::post('join/{code}', [JoinController::class, 'store'])->name('games.join.store');

require __DIR__.'/settings.php';
