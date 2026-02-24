<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GameController extends Controller
{
    public function create()
    {
        return Inertia::render('games/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'max_rounds' => ['required', 'integer', 'in:1,2'],
        ]);

        $game = Game::create([
            'host_user_id' => $request->user()->id,
            'code' => Game::generateUniqueCode(),
            'status' => 'lobby',
            'current_round' => 1,
            'max_rounds' => $validated['max_rounds'],
        ]);

        Player::create([
            'game_id' => $game->id,
            'user_id' => $request->user()->id,
            'name' => $request->user()->name,
            'is_host' => true,
            'has_submitted' => false,
            'score' => 0,
        ]);

        return redirect("/games/{$game->code}/lobby");
    }

    public function lobby(string $code)
    {
        $game = Game::with(['players'])->where('code', $code)->firstOrFail();

        $joinUrl = url("/join/{$code}");

        return Inertia::render('games/Lobby', [
            'game' => $game,
            'joinUrl' => $joinUrl,
        ]);
    }
}
