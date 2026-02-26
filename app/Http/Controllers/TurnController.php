<?php

namespace App\Http\Controllers;

use App\Jobs\TranscribeAudio;
use App\Models\Game;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class TurnController extends Controller
{
    public function show(string $code, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player] = $this->resolvePlayer($game, $request);

        if (! $player) {
            abort(403);
        }

        $currentTurn = $game->turns()
            ->whereIn('status', ['choosing', 'recording'])
            ->with('player')
            ->orderBy('round_number')
            ->orderBy('turn_order')
            ->first();

        if (! $currentTurn) {
            return Inertia::render('games/Play', [
                'game' => [
                    'id' => $game->id,
                    'code' => $game->code,
                    'status' => $game->status,
                    'current_round' => $game->current_round,
                ],
                'player' => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'is_host' => $player->is_host,
                ],
                'currentTurn' => null,
                'isActivePlayer' => false,
            ]);
        }

        $topicChoices = [];
        if ($currentTurn->topic_choices) {
            $topicChoices = Topic::whereIn('id', $currentTurn->topic_choices)
                ->get(['id', 'text'])
                ->toArray();
        }

        $chosenTopicText = null;
        if ($currentTurn->topic_id) {
            $chosenTopicText = Topic::where('id', $currentTurn->topic_id)->value('text');
        }

        return Inertia::render('games/Play', [
            'game' => [
                'id' => $game->id,
                'code' => $game->code,
                'status' => $game->status,
                'current_round' => $game->current_round,
            ],
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'is_host' => $player->is_host,
            ],
            'currentTurn' => [
                'id' => $currentTurn->id,
                'status' => $currentTurn->status,
                'player_name' => $currentTurn->player->name,
                'topic_choices' => $topicChoices,
                'chosen_topic_text' => $chosenTopicText,
            ],
            'isActivePlayer' => $player->id === $currentTurn->player_id,
        ]);
    }

    public function chooseTopic(string $code, int $turnId, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player] = $this->resolvePlayer($game, $request);

        if (! $player) {
            abort(403);
        }

        $turn = $game->turns()->where('id', $turnId)->firstOrFail();

        if ($turn->player_id !== $player->id) {
            abort(403);
        }

        if ($turn->status !== 'choosing') {
            return back()->withErrors(['turn' => 'This turn is not in the choosing state.']);
        }

        $validated = $request->validate([
            'topic_id' => ['required', 'integer'],
        ]);

        if (! in_array($validated['topic_id'], $turn->topic_choices ?? [])) {
            return back()->withErrors(['topic_id' => 'Invalid topic selection.']);
        }

        Topic::where('id', $validated['topic_id'])->update(['is_used' => true]);

        $turn->update([
            'topic_id' => $validated['topic_id'],
            'status' => 'recording',
        ]);

        $game->update(['state_updated_at' => now()]);

        return redirect("/games/{$game->code}/play");
    }

    public function results(string $code, int $turnId, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player] = $this->resolvePlayer($game, $request);

        if (! $player) {
            abort(403);
        }

        $turn = $game->turns()
            ->where('id', $turnId)
            ->with(['player', 'topic'])
            ->firstOrFail();

        $players = $game->players()
            ->orderByDesc('score')
            ->get(['id', 'name', 'score', 'is_host']);

        return Inertia::render('games/Results', [
            'game' => [
                'id' => $game->id,
                'code' => $game->code,
                'status' => $game->status,
                'current_round' => $game->current_round,
            ],
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'is_host' => $player->is_host,
            ],
            'turn' => [
                'id' => $turn->id,
                'player_name' => $turn->player->name,
                'topic_text' => $turn->topic?->text,
                'status' => $turn->status,
                'grade' => $turn->grade,
                'score' => $turn->score,
                'feedback' => $turn->feedback,
                'actual_explanation' => $turn->actual_explanation,
            ],
            'players' => $players->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'score' => $p->score,
                'is_host' => $p->is_host,
            ]),
        ]);
    }

    public function gameState(string $code, Request $request): JsonResponse
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player] = $this->resolvePlayer($game, $request);

        if (! $player) {
            abort(403);
        }

        $stateUpdatedAt = $game->state_updated_at?->timestamp ?? 0;
        $cacheKey = "game_state_{$game->code}_{$stateUpdatedAt}";

        $data = Cache::remember($cacheKey, 1, function () use ($game) {
            return $this->buildGameState($game);
        });

        return response()->json($data);
    }

    private function buildGameState(Game $game): array
    {
        $currentTurn = null;

        $activeTurn = $game->turns()
            ->whereIn('status', ['choosing', 'recording'])
            ->with('player')
            ->orderBy('round_number')
            ->orderBy('turn_order')
            ->first();

        if ($activeTurn) {
            $topicText = null;
            $timeRemaining = null;

            if ($activeTurn->topic_id) {
                $topicText = Topic::where('id', $activeTurn->topic_id)->value('text');
            }

            if ($activeTurn->started_at) {
                $elapsed = max(0, now()->timestamp - $activeTurn->started_at->timestamp);
                $timeRemaining = max(0, 120 - $elapsed);
            }

            $currentTurn = [
                'id' => $activeTurn->id,
                'player_name' => $activeTurn->player->name,
                'topic' => $topicText,
                'status' => $activeTurn->status,
                'time_remaining' => $timeRemaining,
            ];
        }

        $players = $game->players()
            ->orderBy('id')
            ->get(['id', 'name', 'score', 'has_submitted'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'score' => $p->score,
                'has_submitted' => (bool) $p->has_submitted,
            ])
            ->values()
            ->toArray();

        return [
            'game' => [
                'status' => $game->status,
                'current_round' => $game->current_round,
            ],
            'current_turn' => $currentTurn,
            'players' => $players,
            'last_updated' => $game->state_updated_at?->toISOString(),
        ];
    }

    public function playState(string $code, Request $request): JsonResponse
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player] = $this->resolvePlayer($game, $request);

        if (! $player) {
            abort(403);
        }

        $currentTurn = $game->turns()
            ->whereIn('status', ['choosing', 'recording'])
            ->with('player')
            ->orderBy('round_number')
            ->orderBy('turn_order')
            ->first();

        $chosenTopicText = null;
        $chosenTopicPlayerName = null;
        $timeRemaining = null;
        $recordingStarted = false;
        $completedTurnId = null;

        if ($currentTurn && $currentTurn->status === 'recording' && $currentTurn->topic_id) {
            $topic = Topic::find($currentTurn->topic_id);
            $chosenTopicText = $topic?->text;
            $chosenTopicPlayerName = $currentTurn->player?->name;

            if ($currentTurn->started_at) {
                $recordingStarted = true;
                $elapsed = max(0, now()->timestamp - $currentTurn->started_at->timestamp);
                $timeRemaining = max(0, 120 - $elapsed);
            }
        }

        if ($game->status === 'grading_complete') {
            $completedTurn = $game->turns()
                ->where('status', 'complete')
                ->orderByDesc('updated_at')
                ->first();
            $completedTurnId = $completedTurn?->id;
        }

        return response()->json([
            'gameStatus' => $game->status,
            'turnStatus' => $currentTurn?->status,
            'turnId' => $currentTurn?->id,
            'turnPlayerId' => $currentTurn?->player_id,
            'stateUpdatedAt' => $game->state_updated_at?->toISOString(),
            'chosenTopicText' => $chosenTopicText,
            'chosenTopicPlayerName' => $chosenTopicPlayerName,
            'recordingStarted' => $recordingStarted,
            'timeRemaining' => $timeRemaining,
            'completedTurnId' => $completedTurnId,
        ]);
    }

    public function startRecording(string $code, int $turnId, Request $request): JsonResponse
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player] = $this->resolvePlayer($game, $request);

        if (! $player) {
            abort(403);
        }

        $turn = $game->turns()->where('id', $turnId)->firstOrFail();

        if ($turn->player_id !== $player->id) {
            abort(403);
        }

        if ($turn->status !== 'recording') {
            return response()->json(['error' => 'Turn is not in recording state.'], 422);
        }

        $turn->update(['started_at' => now()]);
        $game->update(['state_updated_at' => now()]);

        return response()->json(['started_at' => $turn->fresh()->started_at->toISOString()]);
    }

    public function storeAudio(string $code, int $turnId, Request $request): JsonResponse
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player] = $this->resolvePlayer($game, $request);

        if (! $player) {
            abort(403);
        }

        $turn = $game->turns()->where('id', $turnId)->firstOrFail();

        if ($turn->player_id !== $player->id) {
            abort(403);
        }

        if ($turn->status !== 'recording') {
            return response()->json(['error' => 'Turn is not in recording state.'], 422);
        }

        $request->validate([
            'audio' => ['required', 'file'],
        ]);

        $path = $request->file('audio')->storeAs(
            "audio/{$game->code}",
            "{$turnId}.webm",
            'local'
        );

        $turn->update([
            'audio_path' => $path,
            'status' => 'grading',
            'completed_at' => now(),
        ]);

        $game->update(['state_updated_at' => now()]);

        dispatch(new TranscribeAudio($turn));

        return response()->json(['status' => 'grading']);
    }

    public function advance(string $code, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player, $isHost] = $this->resolvePlayer($game, $request);

        if (! $player || ! $isHost) {
            abort(403);
        }

        $nextTurn = $game->turns()
            ->where('status', 'pending')
            ->where('round_number', $game->current_round)
            ->orderBy('turn_order')
            ->first();

        if ($nextTurn) {
            $nextTurn->update(['status' => 'choosing']);
            $game->update([
                'status' => 'playing',
                'state_updated_at' => now(),
            ]);

            return redirect("/games/{$game->code}/play");
        }

        $game->update([
            'status' => 'round_complete',
            'state_updated_at' => now(),
        ]);

        return redirect("/games/{$game->code}/round-complete");
    }

    public function roundComplete(string $code, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player] = $this->resolvePlayer($game, $request);

        if (! $player) {
            abort(403);
        }

        $players = $game->players()
            ->orderByDesc('score')
            ->get(['id', 'name', 'score', 'is_host']);

        $roundTurns = $game->turns()
            ->where('round_number', $game->current_round)
            ->where('status', 'complete')
            ->with(['player', 'topic'])
            ->orderBy('turn_order')
            ->get();

        return Inertia::render('games/RoundComplete', [
            'game' => [
                'id' => $game->id,
                'code' => $game->code,
                'status' => $game->status,
                'current_round' => $game->current_round,
                'max_rounds' => $game->max_rounds,
            ],
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'is_host' => $player->is_host,
            ],
            'players' => $players->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'score' => $p->score,
                'is_host' => $p->is_host,
            ]),
            'roundTurns' => $roundTurns->map(fn ($t) => [
                'id' => $t->id,
                'player_name' => $t->player->name,
                'topic_text' => $t->topic?->text,
                'grade' => $t->grade,
                'score' => $t->score,
            ]),
        ]);
    }

    public function startNextRound(string $code, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player, $isHost] = $this->resolvePlayer($game, $request);

        if (! $player || ! $isHost) {
            abort(403);
        }

        $nextRound = $game->current_round + 1;

        $firstTurn = $game->turns()
            ->where('round_number', $nextRound)
            ->where('status', 'pending')
            ->orderBy('turn_order')
            ->first();

        if ($firstTurn) {
            $firstTurn->update(['status' => 'choosing']);
        }

        $game->update([
            'status' => 'playing',
            'current_round' => $nextRound,
            'state_updated_at' => now(),
        ]);

        return redirect("/games/{$game->code}/play");
    }

    public function finalizeGame(string $code, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player, $isHost] = $this->resolvePlayer($game, $request);

        if (! $player || ! $isHost) {
            abort(403);
        }

        $game->update([
            'status' => 'complete',
            'state_updated_at' => now(),
        ]);

        return redirect("/games/{$game->code}/complete");
    }

    public function complete(string $code, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player] = $this->resolvePlayer($game, $request);

        if (! $player) {
            abort(403);
        }

        $players = $game->players()
            ->orderByDesc('score')
            ->get(['id', 'name', 'score', 'is_host']);

        $allTurns = $game->turns()
            ->where('status', 'complete')
            ->with(['player', 'topic'])
            ->orderBy('round_number')
            ->orderBy('turn_order')
            ->get();

        return Inertia::render('games/Complete', [
            'game' => [
                'id' => $game->id,
                'code' => $game->code,
                'status' => $game->status,
                'current_round' => $game->current_round,
                'max_rounds' => $game->max_rounds,
            ],
            'player' => [
                'id' => $player->id,
                'name' => $player->name,
                'is_host' => $player->is_host,
            ],
            'players' => $players->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'score' => $p->score,
                'is_host' => $p->is_host,
            ]),
            'allTurns' => $allTurns->map(fn ($t) => [
                'id' => $t->id,
                'player_id' => $t->player_id,
                'player_name' => $t->player->name,
                'topic_text' => $t->topic?->text,
                'grade' => $t->grade,
                'score' => $t->score,
                'round_number' => $t->round_number,
            ]),
        ]);
    }

    public function playAgain(string $code, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        [$player, $isHost] = $this->resolvePlayer($game, $request);

        if (! $player || ! $isHost || ! $request->user()) {
            abort(403);
        }

        $newGame = Game::create([
            'host_user_id' => $request->user()->id,
            'code' => Game::generateUniqueCode(),
            'status' => 'lobby',
            'current_round' => 1,
            'max_rounds' => $game->max_rounds,
            'state_updated_at' => now(),
        ]);

        $newGame->players()->create([
            'user_id' => $request->user()->id,
            'name' => $request->user()->name,
            'is_host' => true,
            'has_submitted' => false,
            'score' => 0,
        ]);

        return redirect("/games/{$newGame->code}/lobby");
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
