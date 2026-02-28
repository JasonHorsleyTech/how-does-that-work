<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Player;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class DevController extends Controller
{
    public function index()
    {
        $users = User::where('email', 'like', 'host-%@dev.test')->get();

        $games = Game::with(['host', 'players'])
            ->whereHas('host', fn ($q) => $q->where('email', 'like', 'host-%@dev.test'))
            ->orderBy('status')
            ->get();

        return view('dev.index', compact('users', 'games'));
    }

    public function loginAs(int $userId)
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $user = User::findOrFail($userId);
        Auth::loginUsingId($userId);

        return redirect('/dashboard');
    }

    public function joinGame(Request $request, string $code)
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $game = Game::where('code', $code)->firstOrFail();

        $player = Player::create([
            'game_id' => $game->id,
            'user_id' => null,
            'name' => $this->randomGuestName(),
            'is_host' => false,
            'has_submitted' => false,
            'score' => 0,
        ]);

        $request->session()->put("player_id.{$code}", $player->id);

        return redirect("/games/{$code}/lobby");
    }

    public function completedTurn()
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $veteran = User::where('email', 'host-veteran@dev.test')->firstOrFail();
        Auth::loginUsingId($veteran->id);

        $game = Game::where('host_user_id', $veteran->id)
            ->where('status', 'complete')
            ->first();

        if (! $game) {
            abort(404, 'No completed game found for host-veteran. Please run db:seed first.');
        }

        $turn = Turn::where('game_id', $game->id)
            ->where('status', 'complete')
            ->orderBy('turn_order')
            ->first();

        if (! $turn) {
            abort(404, 'No completed turn found. Please run db:seed first.');
        }

        return redirect("/games/{$game->code}/results/{$turn->id}");
    }

    public function completedGame()
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $veteran = User::where('email', 'host-veteran@dev.test')->firstOrFail();
        Auth::loginUsingId($veteran->id);

        $game = Game::where('host_user_id', $veteran->id)
            ->where('status', 'complete')
            ->first();

        if (! $game) {
            abort(404, 'No completed game found. Please run db:seed first.');
        }

        return redirect("/games/{$game->code}/complete");
    }

    public function freshDatabase()
    {
        if (app()->environment('production')) {
            abort(404);
        }

        Artisan::call('migrate:fresh', ['--seed' => true]);

        return redirect()->route('dev.index')->with('status', 'Database has been reset and seeded.');
    }

    private function randomGuestName(): string
    {
        $adjectives = ['Sneaky', 'Bold', 'Clever', 'Rapid', 'Gentle', 'Brave', 'Witty', 'Calm', 'Fierce', 'Jolly'];
        $animals = ['Ferret', 'Llama', 'Fox', 'Owl', 'Moose', 'Badger', 'Otter', 'Lynx', 'Panda', 'Raven'];

        return $adjectives[array_rand($adjectives)].' '.$animals[array_rand($animals)];
    }
}
