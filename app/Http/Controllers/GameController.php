<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
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

    public function lobby(string $code, Request $request)
    {
        $game = Game::with(['players'])->where('code', strtoupper($code))->firstOrFail();

        $isHost = false;

        if ($request->user()) {
            $player = $game->players()->where('user_id', $request->user()->id)->first();
            if (! $player) {
                abort(403);
            }
            $isHost = (bool) $player->is_host;
        } else {
            $playerId = $request->session()->get("player_id.{$game->code}");
            if (! $playerId || ! $game->players()->where('id', $playerId)->exists()) {
                abort(403);
            }
        }

        return Inertia::render('games/Lobby', [
            'game' => $game,
            'joinUrl' => url("/join/{$game->code}"),
            'isHost' => $isHost,
        ]);
    }

    public function players(string $code, Request $request): JsonResponse
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        if ($request->user()) {
            $hasAccess = $game->players()->where('user_id', $request->user()->id)->exists();
        } else {
            $playerId = $request->session()->get("player_id.{$game->code}");
            $hasAccess = $playerId && $game->players()->where('id', $playerId)->exists();
        }

        if (! $hasAccess) {
            abort(403);
        }

        $players = $game->players()->get(['id', 'name', 'is_host']);
        $nonHostCount = $players->where('is_host', false)->count();

        return response()->json([
            'players' => $players,
            'nonHostCount' => $nonHostCount,
        ]);
    }
}
