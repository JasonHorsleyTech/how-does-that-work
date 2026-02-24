<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DevController extends Controller
{
    public function index()
    {
        $users = User::whereIn('email', [
            'host-loaded@dev.test',
            'host-standard@dev.test',
            'host-broke@dev.test',
            'host-veteran@dev.test',
        ])->get();

        $games = Game::with(['host', 'players'])
            ->whereHas('host', fn ($q) => $q->whereIn('email', [
                'host-loaded@dev.test',
                'host-standard@dev.test',
                'host-veteran@dev.test',
            ]))
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

        $request->session()->put('player_id', $player->id);

        return redirect("/games/{$code}/lobby");
    }

    private function randomGuestName(): string
    {
        $adjectives = ['Sneaky', 'Bold', 'Clever', 'Rapid', 'Gentle', 'Brave', 'Witty', 'Calm', 'Fierce', 'Jolly'];
        $animals = ['Ferret', 'Llama', 'Fox', 'Owl', 'Moose', 'Badger', 'Otter', 'Lynx', 'Panda', 'Raven'];

        return $adjectives[array_rand($adjectives)].' '.$animals[array_rand($animals)];
    }
}
