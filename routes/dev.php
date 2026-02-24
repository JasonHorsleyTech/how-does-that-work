<?php

use App\Http\Controllers\DevController;
use Illuminate\Support\Facades\Route;

Route::prefix('dev')->group(function () {
    Route::get('/', [DevController::class, 'index'])->name('dev.index');
    Route::get('/login-as/{userId}', [DevController::class, 'loginAs'])->name('dev.login-as');
    Route::get('/join-game/{code}', [DevController::class, 'joinGame'])->name('dev.join-game');
    Route::get('/completed-turn', [DevController::class, 'completedTurn'])->name('dev.completed-turn');
});
