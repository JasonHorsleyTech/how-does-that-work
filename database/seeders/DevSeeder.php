<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('=== Dev Seeder ===');

        // --- Users ---
        $hostLoaded = User::updateOrCreate(
            ['email' => 'host-loaded@dev.test'],
            ['name' => 'Host Loaded', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'credits' => 500]
        );
        $hostStandard = User::updateOrCreate(
            ['email' => 'host-standard@dev.test'],
            ['name' => 'Host Standard', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'credits' => 100]
        );
        $hostBroke = User::updateOrCreate(
            ['email' => 'host-broke@dev.test'],
            ['name' => 'Host Broke', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'credits' => 2]
        );
        $hostVeteran = User::updateOrCreate(
            ['email' => 'host-veteran@dev.test'],
            ['name' => 'Host Veteran', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'credits' => 150]
        );

        $this->command->info('');
        $this->command->info('Seeded accounts:');
        $this->command->table(
            ['Email', 'Password', 'Credits', 'Login URL'],
            [
                [$hostLoaded->email, 'password', $hostLoaded->credits, url("/dev/login-as/{$hostLoaded->id}")],
                [$hostStandard->email, 'password', $hostStandard->credits, url("/dev/login-as/{$hostStandard->id}")],
                [$hostBroke->email, 'password', $hostBroke->credits, url("/dev/login-as/{$hostBroke->id}")],
                [$hostVeteran->email, 'password', $hostVeteran->credits, url("/dev/login-as/{$hostVeteran->id}")],
            ]
        );

        // --- Lobby game (host-standard) ---
        $lobbyCode = $this->uniqueCode();
        $lobbyGame = Game::create([
            'host_user_id' => $hostStandard->id,
            'code' => $lobbyCode,
            'status' => 'lobby',
            'current_round' => 1,
            'max_rounds' => 1,
            'state_updated_at' => now(),
        ]);
        Player::create([
            'game_id' => $lobbyGame->id,
            'user_id' => $hostStandard->id,
            'name' => $hostStandard->name,
            'is_host' => true,
            'has_submitted' => false,
            'score' => 0,
        ]);

        // --- Playing game (host-loaded) ---
        $playingCode = $this->uniqueCode();
        $playingGame = Game::create([
            'host_user_id' => $hostLoaded->id,
            'code' => $playingCode,
            'status' => 'playing',
            'current_round' => 1,
            'max_rounds' => 1,
            'state_updated_at' => now(),
        ]);

        $playingHost = Player::create([
            'game_id' => $playingGame->id,
            'user_id' => $hostLoaded->id,
            'name' => $hostLoaded->name,
            'is_host' => true,
            'has_submitted' => true,
            'score' => 0,
        ]);
        $guest1 = Player::create([
            'game_id' => $playingGame->id,
            'user_id' => null,
            'name' => 'Sneaky Ferret',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 75,
        ]);
        $guest2 = Player::create([
            'game_id' => $playingGame->id,
            'user_id' => null,
            'name' => 'Bold Llama',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 88,
        ]);
        $guest3 = Player::create([
            'game_id' => $playingGame->id,
            'user_id' => null,
            'name' => 'Clever Fox',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 0,
        ]);

        // Topics submitted by each player (3 each)
        $topicHostRefrigerator = Topic::create(['game_id' => $playingGame->id, 'submitted_by_player_id' => $playingHost->id, 'text' => 'How does a refrigerator keep food cold?', 'is_used' => true]);
        Topic::create(['game_id' => $playingGame->id, 'submitted_by_player_id' => $playingHost->id, 'text' => 'How does a car engine convert fuel into motion?', 'is_used' => false]);
        Topic::create(['game_id' => $playingGame->id, 'submitted_by_player_id' => $playingHost->id, 'text' => 'How does a thermostat regulate temperature?', 'is_used' => false]);

        $topicGuest1Glue = Topic::create(['game_id' => $playingGame->id, 'submitted_by_player_id' => $guest1->id, 'text' => 'How does glue work?', 'is_used' => true]);
        Topic::create(['game_id' => $playingGame->id, 'submitted_by_player_id' => $guest1->id, 'text' => 'How does a microwave heat food?', 'is_used' => false]);
        Topic::create(['game_id' => $playingGame->id, 'submitted_by_player_id' => $guest1->id, 'text' => 'How does WiFi transmit data through the air?', 'is_used' => false]);

        Topic::create(['game_id' => $playingGame->id, 'submitted_by_player_id' => $guest2->id, 'text' => 'How does a camera lens focus light onto a sensor?', 'is_used' => false]);
        Topic::create(['game_id' => $playingGame->id, 'submitted_by_player_id' => $guest2->id, 'text' => 'How does bread rise when baked?', 'is_used' => false]);
        Topic::create(['game_id' => $playingGame->id, 'submitted_by_player_id' => $guest2->id, 'text' => 'How does a compass always point north?', 'is_used' => false]);

        Topic::create(['game_id' => $playingGame->id, 'submitted_by_player_id' => $guest3->id, 'text' => 'How does a toilet flush work?', 'is_used' => false]);
        Topic::create(['game_id' => $playingGame->id, 'submitted_by_player_id' => $guest3->id, 'text' => 'How does soap clean dishes?', 'is_used' => false]);
        Topic::create(['game_id' => $playingGame->id, 'submitted_by_player_id' => $guest3->id, 'text' => 'How does a clock keep accurate time?', 'is_used' => false]);

        // Turns: 2 complete, 1 choosing, 1 pending
        Turn::create([
            'game_id' => $playingGame->id,
            'player_id' => $guest1->id,
            'topic_id' => $topicHostRefrigerator->id,
            'round_number' => 1,
            'turn_order' => 1,
            'status' => 'complete',
            'transcript' => 'A refrigerator works by using a coolant fluid that absorbs heat from inside the fridge. The compressor pumps this fluid through coils, and when it expands it gets cold, drawing heat away from the food storage area.',
            'score' => 75,
            'grade' => 'C',
            'feedback' => 'You correctly identified the role of the coolant and compressor. However, you missed explaining the condenser coils outside the unit and the refrigeration cycle in detail.',
            'actual_explanation' => 'A refrigerator works through the refrigeration cycle: a refrigerant fluid is compressed by a motor, raising its temperature. It then flows through condenser coils on the back, releasing heat to the room. The refrigerant expands through an expansion valve, rapidly cooling down, and then absorbs heat from inside the fridge through evaporator coils.',
            'started_at' => now()->subMinutes(15),
            'completed_at' => now()->subMinutes(12),
        ]);
        Turn::create([
            'game_id' => $playingGame->id,
            'player_id' => $guest2->id,
            'topic_id' => $topicGuest1Glue->id,
            'round_number' => 1,
            'turn_order' => 2,
            'status' => 'complete',
            'transcript' => 'Glue works by creating a molecular bond between two surfaces. Most glues contain polymers that flow into tiny surface pores when wet, then harden as they dry, creating a mechanical lock between the surfaces.',
            'score' => 88,
            'grade' => 'B',
            'feedback' => 'Good explanation of mechanical adhesion and polymer behavior. You could have mentioned chemical adhesion and the role of surface energy for a more complete answer.',
            'actual_explanation' => 'Glue works through two main mechanisms: mechanical adhesion, where liquid glue flows into microscopic pores and hardens to create a physical lock, and chemical adhesion, where molecules in the glue form chemical bonds with molecules on the surface. Different glues use different chemistries — super glue uses cyanoacrylate that polymerizes on contact with moisture, while wood glue uses PVA polymers.',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(7),
        ]);
        Turn::create([
            'game_id' => $playingGame->id,
            'player_id' => $playingHost->id,
            'topic_id' => null,
            'round_number' => 1,
            'turn_order' => 3,
            'status' => 'choosing',
            'started_at' => now()->subMinutes(2),
        ]);
        Turn::create([
            'game_id' => $playingGame->id,
            'player_id' => $guest3->id,
            'topic_id' => null,
            'round_number' => 1,
            'turn_order' => 4,
            'status' => 'pending',
        ]);

        // --- Completed game (host-veteran) ---
        $completedCode = $this->uniqueCode();
        $completedGame = Game::create([
            'host_user_id' => $hostVeteran->id,
            'code' => $completedCode,
            'status' => 'complete',
            'current_round' => 1,
            'max_rounds' => 1,
            'state_updated_at' => now()->subHours(2),
        ]);

        $veteranPlayer = Player::create([
            'game_id' => $completedGame->id,
            'user_id' => $hostVeteran->id,
            'name' => $hostVeteran->name,
            'is_host' => true,
            'has_submitted' => true,
            'score' => 85,
        ]);
        $completedGuest1 = Player::create([
            'game_id' => $completedGame->id,
            'user_id' => null,
            'name' => 'Rapid Owl',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 92,
        ]);
        $completedGuest2 = Player::create([
            'game_id' => $completedGame->id,
            'user_id' => null,
            'name' => 'Gentle Moose',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 67,
        ]);

        // Topics for completed game
        $cTopic1 = Topic::create(['game_id' => $completedGame->id, 'submitted_by_player_id' => $veteranPlayer->id, 'text' => 'How does a steam engine work?', 'is_used' => true]);
        Topic::create(['game_id' => $completedGame->id, 'submitted_by_player_id' => $veteranPlayer->id, 'text' => 'How does a radio antenna receive signals?', 'is_used' => false]);
        Topic::create(['game_id' => $completedGame->id, 'submitted_by_player_id' => $veteranPlayer->id, 'text' => 'How do airplane wings generate lift?', 'is_used' => false]);

        $cTopic2 = Topic::create(['game_id' => $completedGame->id, 'submitted_by_player_id' => $completedGuest1->id, 'text' => 'How does a battery store electricity?', 'is_used' => true]);
        Topic::create(['game_id' => $completedGame->id, 'submitted_by_player_id' => $completedGuest1->id, 'text' => 'How does a speaker produce sound?', 'is_used' => false]);
        Topic::create(['game_id' => $completedGame->id, 'submitted_by_player_id' => $completedGuest1->id, 'text' => 'How does the internet route data packets?', 'is_used' => false]);

        $cTopic3 = Topic::create(['game_id' => $completedGame->id, 'submitted_by_player_id' => $completedGuest2->id, 'text' => 'How does a vaccine teach the immune system?', 'is_used' => true]);
        Topic::create(['game_id' => $completedGame->id, 'submitted_by_player_id' => $completedGuest2->id, 'text' => 'How does GPS know your location?', 'is_used' => false]);
        Topic::create(['game_id' => $completedGame->id, 'submitted_by_player_id' => $completedGuest2->id, 'text' => 'How does a touchscreen detect finger presses?', 'is_used' => false]);

        // Turns for completed game (all complete)
        Turn::create([
            'game_id' => $completedGame->id,
            'player_id' => $completedGuest1->id,
            'topic_id' => $cTopic1->id,
            'round_number' => 1,
            'turn_order' => 1,
            'status' => 'complete',
            'transcript' => 'A steam engine heats water to produce steam, which expands and pushes a piston back and forth. The piston movement is converted to rotational motion via a crankshaft, powering wheels or machinery.',
            'score' => 92,
            'grade' => 'A',
            'feedback' => 'Excellent explanation covering the key components. You correctly described the steam expansion, piston action, and conversion to rotational motion. Mentioning the condenser for steam recycling would have made it perfect.',
            'actual_explanation' => 'A steam engine burns fuel to heat water in a boiler until it becomes steam. The high-pressure steam is directed into a cylinder where it expands and pushes a piston. The piston is connected to a crankshaft that converts the back-and-forth linear motion into rotation, which can power wheels or machinery. In condensing engines, the spent steam is cooled back to water and recycled.',
            'started_at' => now()->subHours(3),
            'completed_at' => now()->subHours(2)->subMinutes(50),
        ]);
        Turn::create([
            'game_id' => $completedGame->id,
            'player_id' => $completedGuest2->id,
            'topic_id' => $cTopic2->id,
            'round_number' => 1,
            'turn_order' => 2,
            'status' => 'complete',
            'transcript' => 'A battery stores energy in chemical form. When connected to a circuit, a chemical reaction releases electrons that flow through the wire as electricity. When the chemicals are used up the battery is dead.',
            'score' => 67,
            'grade' => 'D',
            'feedback' => 'You captured the basic idea but lacked precision. The mention of electrons is correct but the explanation of how the two electrodes and electrolyte work together was missing.',
            'actual_explanation' => 'A battery stores energy as chemical potential energy. Inside are two electrodes (anode and cathode) separated by an electrolyte. A chemical oxidation reaction at the anode releases electrons, which flow through an external circuit to the cathode, creating electrical current. At the cathode, a reduction reaction absorbs those electrons. In rechargeable batteries, applying external current reverses these reactions.',
            'started_at' => now()->subHours(2)->subMinutes(48),
            'completed_at' => now()->subHours(2)->subMinutes(38),
        ]);
        Turn::create([
            'game_id' => $completedGame->id,
            'player_id' => $veteranPlayer->id,
            'topic_id' => $cTopic3->id,
            'round_number' => 1,
            'turn_order' => 3,
            'status' => 'complete',
            'transcript' => 'A vaccine introduces a weakened or dead version of a pathogen, or a piece of it, into the body. The immune system recognizes it as foreign and builds antibodies. If the real pathogen appears later the immune system remembers and responds quickly.',
            'score' => 85,
            'grade' => 'B',
            'feedback' => 'Solid answer with a good grasp of immune memory and the purpose of vaccines. You could improve by distinguishing between different vaccine types like live-attenuated, inactivated, and mRNA vaccines.',
            'actual_explanation' => 'A vaccine stimulates the immune system by introducing an antigen — this could be a weakened or killed pathogen, a piece of it like a protein, or genetic instructions (mRNA) for cells to make that protein. The immune system recognizes the antigen as foreign and mounts a response, producing antibodies and creating memory B and T cells. If the actual pathogen is encountered later, these memory cells trigger a rapid, strong immune response before illness takes hold.',
            'started_at' => now()->subHours(2)->subMinutes(36),
            'completed_at' => now()->subHours(2)->subMinutes(26),
        ]);

        $this->command->info('');
        $this->command->info('Seeded games:');
        $this->command->table(
            ['Status', 'Code', 'Owner', 'Join/View URL'],
            [
                ['lobby', $lobbyCode, 'host-standard@dev.test', url("/dev/join-game/{$lobbyCode}")],
                ['playing', $playingCode, 'host-loaded@dev.test', url("/dev/join-game/{$playingCode}")],
                ['complete', $completedCode, 'host-veteran@dev.test', url("/games/{$completedCode}/lobby")],
            ]
        );

        $this->command->info('');
        $this->command->info('Dev dashboard: '.url('/dev'));
        $this->command->info('');
    }

    private function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Game::where('code', $code)->exists());

        return $code;
    }
}
