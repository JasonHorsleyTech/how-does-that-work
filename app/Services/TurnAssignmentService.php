<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Turn;
use Illuminate\Support\Collection;

class TurnAssignmentService
{
    /**
     * Generate turn records for a game when it transitions to 'playing'.
     *
     * For each round:
     *   - All players (including the host) are shuffled to determine turn order.
     *   - Each player receives up to 2 topic choices: topics they did not submit,
     *     and not already claimed by another turn in this generation pass.
     *   - If 0 eligible topics remain for a player, that player's turn is skipped.
     *
     * Topics are "claimed" in memory during generation so the same topic is never
     * placed in two different turns' topic_choices.
     */
    public function assignTurns(Game $game): void
    {
        $players = $game->players()
            ->get()
            ->shuffle()
            ->values();

        $topics = $game->topics()->get(['id', 'submitted_by_player_id']);

        $claimedIds = [];

        for ($round = 1; $round <= $game->max_rounds; $round++) {
            foreach ($players as $turnOrder => $player) {
                $choices = $this->pickChoices($topics, $player->id, $claimedIds);

                if ($choices->isEmpty()) {
                    continue;
                }

                $choiceIds = $choices->pluck('id')->all();
                $claimedIds = array_merge($claimedIds, $choiceIds);

                Turn::create([
                    'game_id' => $game->id,
                    'player_id' => $player->id,
                    'topic_choices' => $choiceIds,
                    'round_number' => $round,
                    'turn_order' => $turnOrder + 1,
                    'status' => 'pending',
                ]);
            }
        }
    }

    private function pickChoices(Collection $topics, int $playerId, array $claimedIds): Collection
    {
        return $topics
            ->filter(fn ($t) => $t->submitted_by_player_id !== $playerId && ! in_array($t->id, $claimedIds))
            ->shuffle()
            ->take(2)
            ->values();
    }
}
