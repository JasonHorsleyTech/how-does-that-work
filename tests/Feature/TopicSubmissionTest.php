<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Start submission phase ---

test('host can start the submission phase', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'lobby']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true]);
    Player::factory()->count(2)->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false]);

    $response = $this->post(route('games.start-submission', ['code' => $game->code]));

    $response->assertRedirect("/games/{$game->code}/submit");
    $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'submitting']);
});

test('host can start submission solo with 0 non-host players', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'lobby']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true]);

    $response = $this->post(route('games.start-submission', ['code' => $game->code]));

    $response->assertRedirect("/games/{$game->code}/submit");
    $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'submitting']);
});

test('host can start submission with 1 non-host player', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'lobby']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true]);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false]);

    $response = $this->post(route('games.start-submission', ['code' => $game->code]));

    $response->assertRedirect("/games/{$game->code}/submit");
    $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'submitting']);
});

test('non-host cannot start submission phase', function () {
    $hostUser = User::factory()->create();
    $guestUser = User::factory()->create();
    $this->actingAs($guestUser);

    $game = Game::factory()->create(['host_user_id' => $hostUser->id, 'status' => 'lobby']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $hostUser->id, 'is_host' => true]);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $guestUser->id, 'is_host' => false]);

    $response = $this->post(route('games.start-submission', ['code' => $game->code]));

    $response->assertForbidden();
});

// --- Submit page ---

test('authenticated host can view the submit page when game is submitting', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'submitting']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true, 'has_submitted' => false]);

    $response = $this->get(route('games.submit', ['code' => $game->code]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('games/Submit')
        ->where('player.is_host', true)
        ->where('player.has_submitted', false)
        ->has('submittedCount')
        ->has('totalCount')
    );
});

test('guest player with valid session can view the submit page', function () {
    $game = Game::factory()->create(['status' => 'submitting']);
    $player = Player::factory()->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false, 'has_submitted' => false]);

    $response = $this->withSession(["player_id.{$game->code}" => $player->id])
        ->get(route('games.submit', ['code' => $game->code]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('games/Submit')
        ->where('player.has_submitted', false)
    );
});

test('unauthenticated user without session is forbidden from the submit page', function () {
    $game = Game::factory()->create(['status' => 'submitting']);

    $response = $this->get(route('games.submit', ['code' => $game->code]));

    $response->assertForbidden();
});

test('submit page redirects to lobby if game is not in submitting status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'lobby']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true]);

    $response = $this->get(route('games.submit', ['code' => $game->code]));

    $response->assertRedirect("/games/{$game->code}/lobby");
});

// --- Topic submission ---

test('authenticated host can submit three topics', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'submitting']);
    $player = Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true, 'has_submitted' => false]);

    $response = $this->post(route('games.topics.store', ['code' => $game->code]), [
        'topics' => [
            'How does a microwave work?',
            'How does glue work?',
            'How does a pipe organ work?',
        ],
    ]);

    $response->assertRedirect("/games/{$game->code}/submit");
    $this->assertDatabaseHas('players', ['id' => $player->id, 'has_submitted' => true]);
    $this->assertDatabaseCount('topics', 3);
    $this->assertDatabaseHas('topics', [
        'game_id' => $game->id,
        'submitted_by_player_id' => $player->id,
        'text' => 'How does a microwave work?',
    ]);
});

test('guest player can submit three topics via session', function () {
    $game = Game::factory()->create(['status' => 'submitting']);
    $player = Player::factory()->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false, 'has_submitted' => false]);

    $response = $this->withSession(["player_id.{$game->code}" => $player->id])
        ->post(route('games.topics.store', ['code' => $game->code]), [
            'topics' => [
                'How does a microwave work?',
                'How does glue work?',
                'How does a pipe organ work?',
            ],
        ]);

    $response->assertRedirect("/games/{$game->code}/submit");
    $this->assertDatabaseHas('players', ['id' => $player->id, 'has_submitted' => true]);
    expect(Topic::where('submitted_by_player_id', $player->id)->count())->toBe(3);
});

test('player cannot submit topics more than once', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'submitting']);
    $player = Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true, 'has_submitted' => true]);

    $response = $this->post(route('games.topics.store', ['code' => $game->code]), [
        'topics' => [
            'How does a microwave work?',
            'How does glue work?',
            'How does a pipe organ work?',
        ],
    ]);

    $response->assertSessionHasErrors('game');
    $this->assertDatabaseCount('topics', 0);
});

test('topics must be between 1 and 120 characters', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'submitting']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true, 'has_submitted' => false]);

    $response = $this->post(route('games.topics.store', ['code' => $game->code]), [
        'topics' => [
            '',
            str_repeat('x', 121),
            'How does a pipe organ work?',
        ],
    ]);

    $response->assertSessionHasErrors(['topics.0', 'topics.1']);
    $this->assertDatabaseCount('topics', 0);
});

test('exactly three topics must be submitted', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'submitting']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true, 'has_submitted' => false]);

    $response = $this->post(route('games.topics.store', ['code' => $game->code]), [
        'topics' => [
            'How does a microwave work?',
            'How does glue work?',
        ],
    ]);

    $response->assertSessionHasErrors('topics');
    $this->assertDatabaseCount('topics', 0);
});

test('cannot submit topics when game is not in submitting status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'lobby']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true, 'has_submitted' => false]);

    $response = $this->post(route('games.topics.store', ['code' => $game->code]), [
        'topics' => [
            'How does a microwave work?',
            'How does glue work?',
            'How does a pipe organ work?',
        ],
    ]);

    $response->assertSessionHasErrors('game');
    $this->assertDatabaseCount('topics', 0);
});

// --- Submission status endpoint ---

test('submission status endpoint returns correct counts', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'submitting']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true, 'has_submitted' => true]);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false, 'has_submitted' => false]);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false, 'has_submitted' => true]);

    $response = $this->getJson(route('games.submission-status', ['code' => $game->code]));

    $response->assertOk();
    $response->assertJson([
        'submittedCount' => 2,
        'totalCount' => 3,
        'gameStatus' => 'submitting',
    ]);
});
