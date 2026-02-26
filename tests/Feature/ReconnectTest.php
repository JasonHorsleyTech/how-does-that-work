<?php

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('player can reconnect with valid token and active session', function () {
    $game = Game::factory()->create(['status' => 'playing']);

    $reconnectToken = Str::uuid()->toString();
    $player = Player::factory()->create([
        'game_id' => $game->id,
        'is_host' => false,
        'user_id' => null,
        'reconnect_token' => $reconnectToken,
        'updated_at' => now(),
    ]);

    $response = $this->postJson("/join/{$game->code}/reconnect", [
        'reconnect_token' => $reconnectToken,
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'redirect_url' => "/games/{$game->code}/play",
    ]);

    // Verify session was restored
    $response->assertSessionHas("player_id.{$game->code}", $player->id);
});

test('reconnect fails with invalid token', function () {
    $game = Game::factory()->create(['status' => 'playing']);

    Player::factory()->create([
        'game_id' => $game->id,
        'is_host' => false,
        'user_id' => null,
        'reconnect_token' => Str::uuid()->toString(),
    ]);

    $response = $this->postJson("/join/{$game->code}/reconnect", [
        'reconnect_token' => 'invalid-token',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => false]);
});

test('reconnect fails when token is stale (older than 10 minutes)', function () {
    $game = Game::factory()->create(['status' => 'playing']);

    $reconnectToken = Str::uuid()->toString();
    Player::factory()->create([
        'game_id' => $game->id,
        'is_host' => false,
        'user_id' => null,
        'reconnect_token' => $reconnectToken,
        'updated_at' => now()->subMinutes(11),
    ]);

    $response = $this->postJson("/join/{$game->code}/reconnect", [
        'reconnect_token' => $reconnectToken,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => false]);
});

test('reconnect returns correct redirect URL based on game status', function () {
    $game = Game::factory()->create(['status' => 'submitting']);

    $reconnectToken = Str::uuid()->toString();
    Player::factory()->create([
        'game_id' => $game->id,
        'is_host' => false,
        'user_id' => null,
        'reconnect_token' => $reconnectToken,
        'updated_at' => now(),
    ]);

    $response = $this->postJson("/join/{$game->code}/reconnect", [
        'reconnect_token' => $reconnectToken,
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'redirect_url' => "/games/{$game->code}/submit",
    ]);
});

test('reconnect does not match host players (with user_id)', function () {
    $game = Game::factory()->create(['status' => 'playing']);

    $reconnectToken = Str::uuid()->toString();
    Player::factory()->create([
        'game_id' => $game->id,
        'is_host' => true,
        'user_id' => 1,
        'reconnect_token' => $reconnectToken,
        'updated_at' => now(),
    ]);

    $response = $this->postJson("/join/{$game->code}/reconnect", [
        'reconnect_token' => $reconnectToken,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => false]);
});

test('full reconnect flow: join, lose session, reconnect', function () {
    $game = Game::factory()->create(['status' => 'lobby']);

    // Step 1: Player joins the game
    $joinResponse = $this->post("/join/{$game->code}", ['name' => 'Bold Llama']);
    $joinResponse->assertRedirect("/games/{$game->code}/lobby");

    $player = Player::where('game_id', $game->id)->where('name', 'Bold Llama')->first();
    expect($player)->not->toBeNull();
    expect($player->reconnect_token)->not->toBeNull();

    $reconnectToken = $player->reconnect_token;

    // Step 2: Simulate session loss by flushing the session
    $this->flushSession();

    // Step 3: Attempt reconnect with the saved token
    $reconnectResponse = $this->postJson("/join/{$game->code}/reconnect", [
        'reconnect_token' => $reconnectToken,
    ]);

    $reconnectResponse->assertOk();
    $reconnectResponse->assertJson([
        'success' => true,
        'redirect_url' => "/games/{$game->code}/lobby",
    ]);

    // Step 4: Verify session was restored — player can access the lobby
    $reconnectResponse->assertSessionHas("player_id.{$game->code}", $player->id);
});

test('reconnect requires reconnect_token field', function () {
    $game = Game::factory()->create(['status' => 'lobby']);

    $response = $this->postJson("/join/{$game->code}/reconnect", []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('reconnect_token');
});

test('joining a game stores reconnect data in session flash', function () {
    $game = Game::factory()->create(['status' => 'lobby']);

    $response = $this->post("/join/{$game->code}", ['name' => 'Sneaky Ferret']);

    $player = Player::where('game_id', $game->id)->where('name', 'Sneaky Ferret')->first();

    $response->assertSessionHas('reconnect_data', [
        'reconnect_token' => $player->reconnect_token,
        'game_code' => $game->code,
        'player_id' => $player->id,
    ]);
});
