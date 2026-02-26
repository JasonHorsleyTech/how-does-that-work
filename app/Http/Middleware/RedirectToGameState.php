<?php

namespace App\Http\Middleware;

use App\Models\Game;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToGameState
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to Inertia page requests, not JSON/API endpoints
        if ($request->expectsJson() || $request->is('api/*')) {
            return $next($request);
        }

        $code = $request->route('code');
        if (! $code) {
            return $next($request);
        }

        $game = Game::where('code', strtoupper($code))->first();
        if (! $game) {
            return $next($request);
        }

        // Check if user is a participant — if not, let the controller handle 403
        if (! $this->isParticipant($game, $request)) {
            return $next($request);
        }

        $correctUrl = static::correctUrlForStatus($game);
        if (! $correctUrl) {
            return $next($request);
        }

        // Don't redirect if already on the correct URL
        if ($request->is(ltrim($correctUrl, '/'))) {
            return $next($request);
        }

        // Also allow results pages during grading_complete (the URL contains a turnId)
        if ($game->status === 'grading_complete' && $request->is("games/{$game->code}/results/*")) {
            return $next($request);
        }

        return redirect($correctUrl);
    }

    private function isParticipant(Game $game, Request $request): bool
    {
        if ($request->user()) {
            return $game->players()->where('user_id', $request->user()->id)->exists();
        }

        $playerId = $request->session()->get("player_id.{$game->code}");

        return $playerId && $game->players()->where('id', $playerId)->exists();
    }

    public static function correctUrlForStatus(Game $game): ?string
    {
        return match ($game->status) {
            'lobby' => "/games/{$game->code}/lobby",
            'submitting' => "/games/{$game->code}/submit",
            'playing' => "/games/{$game->code}/play",
            'grading' => "/games/{$game->code}/play",
            'grading_complete' => static::gradingCompleteUrl($game),
            'round_complete' => "/games/{$game->code}/round-complete",
            'complete' => "/games/{$game->code}/complete",
            default => null,
        };
    }

    private static function gradingCompleteUrl(Game $game): string
    {
        $completedTurn = $game->turns()
            ->where('status', 'complete')
            ->orderByDesc('updated_at')
            ->first();

        if ($completedTurn) {
            return "/games/{$game->code}/results/{$completedTurn->id}";
        }

        // Fallback: shouldn't happen in practice
        return "/games/{$game->code}/play";
    }
}
