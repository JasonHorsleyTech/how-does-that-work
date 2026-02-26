<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Player;
use App\Services\TurnAssignmentService;
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

        $playerId = null;
        if ($request->user()) {
            $hasAccess = $game->players()->where('user_id', $request->user()->id)->exists();
        } else {
            $playerId = $request->session()->get("player_id.{$game->code}");
            $hasAccess = $playerId && $game->players()->where('id', $playerId)->exists();
        }

        if (! $hasAccess) {
            abort(403);
        }

        // Touch player updated_at to keep reconnect window alive
        if ($playerId) {
            Player::where('id', $playerId)->update(['updated_at' => now()]);
        }

        $players = $game->players()->get(['id', 'name', 'is_host']);
        $nonHostCount = $players->where('is_host', false)->count();

        return response()->json([
            'players' => $players,
            'nonHostCount' => $nonHostCount,
            'gameStatus' => $game->status,
        ]);
    }

    public function startSubmission(string $code, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        $player = $game->players()->where('user_id', $request->user()->id)->first();
        if (! $player || ! $player->is_host) {
            abort(403);
        }

        if ($game->status !== 'lobby') {
            return back()->withErrors(['game' => 'Game is not in lobby status.']);
        }

        $nonHostCount = $game->players()->where('is_host', false)->count();
        if ($nonHostCount < 2) {
            return back()->withErrors(['game' => 'At least 2 non-host players must join before starting.']);
        }

        $game->update([
            'status' => 'submitting',
            'state_updated_at' => now(),
        ]);

        return redirect("/games/{$game->code}/submit");
    }

    public function submissionStatus(string $code, Request $request): JsonResponse
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        $playerId = null;
        if ($request->user()) {
            $hasAccess = $game->players()->where('user_id', $request->user()->id)->exists();
        } else {
            $playerId = $request->session()->get("player_id.{$game->code}");
            $hasAccess = $playerId && $game->players()->where('id', $playerId)->exists();
        }

        if (! $hasAccess) {
            abort(403);
        }

        // Touch player updated_at to keep reconnect window alive
        if ($playerId) {
            Player::where('id', $playerId)->update(['updated_at' => now()]);
        }

        $players = $game->players()->get(['id', 'name', 'has_submitted']);
        $submittedCount = $players->where('has_submitted', true)->count();
        $totalCount = $players->count();

        return response()->json([
            'submittedCount' => $submittedCount,
            'totalCount' => $totalCount,
            'gameStatus' => $game->status,
            'players' => $players->map(fn ($p) => ['name' => $p->name, 'has_submitted' => (bool) $p->has_submitted]),
        ]);
    }

    public function startGame(string $code, Request $request, TurnAssignmentService $turnAssignment)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        $player = $game->players()->where('user_id', $request->user()->id)->first();
        if (! $player || ! $player->is_host) {
            abort(403);
        }

        if ($game->status !== 'submitting') {
            return back()->withErrors(['game' => 'Game is not in the submission phase.']);
        }

        if ($request->user()->credits <= 0) {
            return back()->withErrors(['game' => 'Add credits to start a game.']);
        }

        $game->update([
            'status' => 'playing',
            'state_updated_at' => now(),
        ]);

        $turnAssignment->assignTurns($game);

        // Activate the first turn so the active player can choose their topic
        $game->turns()
            ->where('round_number', 1)
            ->orderBy('turn_order')
            ->first()
            ?->update(['status' => 'choosing']);

        return redirect("/games/{$game->code}/play");
    }
}
