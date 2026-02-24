<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JoinController extends Controller
{
    private const MAX_PLAYERS = 10;

    private const ADJECTIVES = [
        'Bold', 'Sneaky', 'Brave', 'Fuzzy', 'Dizzy', 'Spunky', 'Zippy',
        'Grumpy', 'Peppy', 'Silly', 'Wacky', 'Clumsy', 'Fluffy', 'Snappy',
        'Bouncy', 'Crafty', 'Goofy', 'Jolly', 'Moody', 'Nervy',
    ];

    private const ANIMALS = [
        'Ferret', 'Llama', 'Badger', 'Penguin', 'Wombat', 'Capybara',
        'Platypus', 'Gecko', 'Narwhal', 'Quokka', 'Axolotl', 'Tapir',
        'Pangolin', 'Ocelot', 'Meerkat', 'Binturong', 'Fossa', 'Kinkajou',
        'Numbat', 'Saiga',
    ];

    public function show(string $code)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        if ($game->status !== 'lobby') {
            return Inertia::render('games/Join', [
                'game' => ['code' => $game->code],
                'error' => 'This game has already started',
                'suggestedName' => null,
            ]);
        }

        if ($game->players()->count() >= self::MAX_PLAYERS) {
            return Inertia::render('games/Join', [
                'game' => ['code' => $game->code],
                'error' => 'This game is full',
                'suggestedName' => null,
            ]);
        }

        return Inertia::render('games/Join', [
            'game' => ['code' => $game->code],
            'error' => null,
            'suggestedName' => $this->randomName(),
        ]);
    }

    public function store(string $code, Request $request)
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();

        if ($game->status !== 'lobby') {
            return back()->withErrors(['game' => 'This game has already started']);
        }

        if ($game->players()->count() >= self::MAX_PLAYERS) {
            return back()->withErrors(['game' => 'This game is full']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:50'],
        ]);

        $player = Player::create([
            'game_id' => $game->id,
            'user_id' => null,
            'name' => trim($validated['name']),
            'is_host' => false,
            'has_submitted' => false,
            'score' => 0,
        ]);

        $request->session()->put("player_id.{$game->code}", $player->id);

        return redirect("/games/{$game->code}/lobby");
    }

    private function randomName(): string
    {
        $adjective = self::ADJECTIVES[array_rand(self::ADJECTIVES)];
        $animal = self::ANIMALS[array_rand(self::ANIMALS)];

        return "{$adjective} {$animal}";
    }
}
