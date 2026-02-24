<?php

namespace App\Http\Controllers;

use App\Jobs\TranscribeAudio;
use App\Models\Game;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        if ($game->status !== 'playing') {
            return redirect("/games/{$game->code}/lobby");
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
            ->orderBy('round_number')
            ->orderBy('turn_order')
            ->first();

        if ($nextTurn) {
            $nextTurn->update(['status' => 'choosing']);
            $game->update([
                'status' => 'playing',
                'state_updated_at' => now(),
            ]);
        } else {
            $game->update([
                'status' => 'round_complete',
                'state_updated_at' => now(),
            ]);
        }

        return redirect("/games/{$game->code}/play");
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
