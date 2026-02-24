<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();

        $games = $user->games()
            ->with('players')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($game) {
                $winner = $game->players->sortByDesc('score')->first();

                return [
                    'id' => $game->id,
                    'code' => $game->code,
                    'status' => $game->status,
                    'created_at' => $game->created_at->toIso8601String(),
                    'player_count' => $game->players->count(),
                    'winner' => $winner ? ['name' => $winner->name, 'score' => $winner->score] : null,
                ];
            });

        return Inertia::render('Dashboard', ['games' => $games]);
    }
}
