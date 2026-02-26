<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Topic;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TopicController extends Controller
{
    public function show(string $code, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player] = $this->resolvePlayer($game, $request);

        if (! $player) {
            abort(403);
        }

        $allPlayers = $game->players()->get(['id', 'name', 'has_submitted']);
        $submittedCount = $allPlayers->where('has_submitted', true)->count();
        $totalCount = $allPlayers->count();

        // Pass host credits so the Start Game button can be disabled when credits = 0
        $hostCredits = null;
        if ($player->is_host && $request->user()) {
            $hostCredits = $request->user()->credits;
        }

        return Inertia::render('games/Submit', [
            'game' => [
                'id' => $game->id,
                'code' => $game->code,
                'status' => $game->status,
            ],
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'has_submitted' => $player->has_submitted,
                'is_host' => $player->is_host,
            ],
            'submittedCount' => $submittedCount,
            'totalCount' => $totalCount,
            'players' => $allPlayers->map(fn ($p) => ['name' => $p->name, 'has_submitted' => (bool) $p->has_submitted]),
            'hostCredits' => $hostCredits,
        ]);
    }

    public function store(string $code, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player] = $this->resolvePlayer($game, $request);

        if (! $player) {
            abort(403);
        }

        if ($game->status !== 'submitting') {
            return back()->withErrors(['game' => 'The submission phase has not started.']);
        }

        if ($player->has_submitted) {
            return back()->withErrors(['game' => 'You have already submitted your topics.']);
        }

        $validated = $request->validate([
            'topics' => ['required', 'array', 'size:3'],
            'topics.*' => ['required', 'string', 'min:5', 'max:120'],
        ]);

        foreach ($validated['topics'] as $text) {
            Topic::create([
                'game_id' => $game->id,
                'submitted_by_player_id' => $player->id,
                'text' => trim($text),
                'is_used' => false,
            ]);
        }

        $player->update(['has_submitted' => true]);

        return redirect("/games/{$game->code}/submit");
    }

    private function resolvePlayer(Game $game, Request $request): array
    {
        if ($request->user()) {
            $player = $game->players()->where('user_id', $request->user()->id)->first();

            return [$player, $player?->is_host ?? false];
        }

        $playerId = $request->session()->get("player_id.{$game->code}");
        if ($playerId) {
            $player = $game->players()->where('id', $playerId)->first();

            return [$player, false];
        }

        return [null, false];
    }
}
