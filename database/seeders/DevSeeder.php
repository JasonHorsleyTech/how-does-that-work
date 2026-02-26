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

        // Scenario hosts for E2E testing
        $hostSubmitting = User::updateOrCreate(
            ['email' => 'host-submitting@dev.test'],
            ['name' => 'Host Submitting', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'credits' => 100]
        );
        $hostReady = User::updateOrCreate(
            ['email' => 'host-ready@dev.test'],
            ['name' => 'Host Ready', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'credits' => 100]
        );
        $hostChoosing = User::updateOrCreate(
            ['email' => 'host-choosing@dev.test'],
            ['name' => 'Host Choosing', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'credits' => 100]
        );
        $hostGradingDone = User::updateOrCreate(
            ['email' => 'host-grading-done@dev.test'],
            ['name' => 'Host Grading Done', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'credits' => 100]
        );
        $hostRoundDone = User::updateOrCreate(
            ['email' => 'host-round-done@dev.test'],
            ['name' => 'Host Round Done', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'credits' => 100]
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
                [$hostSubmitting->email, 'password', $hostSubmitting->credits, url("/dev/login-as/{$hostSubmitting->id}")],
                [$hostReady->email, 'password', $hostReady->credits, url("/dev/login-as/{$hostReady->id}")],
                [$hostChoosing->email, 'password', $hostChoosing->credits, url("/dev/login-as/{$hostChoosing->id}")],
                [$hostGradingDone->email, 'password', $hostGradingDone->credits, url("/dev/login-as/{$hostGradingDone->id}")],
                [$hostRoundDone->email, 'password', $hostRoundDone->credits, url("/dev/login-as/{$hostRoundDone->id}")],
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

        // --- Scenario 1: Submitting phase — host has NOT submitted yet ---
        // Log in as host-submitting@dev.test → should see topic submission form at /games/{code}/submit
        $submittingCode = $this->uniqueCode();
        $submittingGame = Game::create([
            'host_user_id' => $hostSubmitting->id,
            'code' => $submittingCode,
            'status' => 'submitting',
            'current_round' => 1,
            'max_rounds' => 1,
            'state_updated_at' => now(),
        ]);
        Player::create([
            'game_id' => $submittingGame->id,
            'user_id' => $hostSubmitting->id,
            'name' => $hostSubmitting->name,
            'is_host' => true,
            'has_submitted' => false,
            'score' => 0,
        ]);
        Player::create([
            'game_id' => $submittingGame->id,
            'user_id' => null,
            'name' => 'Witty Otter',
            'is_host' => false,
            'has_submitted' => false,
            'score' => 0,
        ]);
        Player::create([
            'game_id' => $submittingGame->id,
            'user_id' => null,
            'name' => 'Fierce Lynx',
            'is_host' => false,
            'has_submitted' => false,
            'score' => 0,
        ]);

        // --- Scenario 2: Submitting phase — all submitted, host ready to start ---
        // Log in as host-ready@dev.test → should see "Start Game" button at /games/{code}/submit
        $readyCode = $this->uniqueCode();
        $readyGame = Game::create([
            'host_user_id' => $hostReady->id,
            'code' => $readyCode,
            'status' => 'submitting',
            'current_round' => 1,
            'max_rounds' => 1,
            'state_updated_at' => now(),
        ]);
        $readyHost = Player::create([
            'game_id' => $readyGame->id,
            'user_id' => $hostReady->id,
            'name' => $hostReady->name,
            'is_host' => true,
            'has_submitted' => true,
            'score' => 0,
        ]);
        $readyGuest1 = Player::create([
            'game_id' => $readyGame->id,
            'user_id' => null,
            'name' => 'Brave Badger',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 0,
        ]);
        $readyGuest2 = Player::create([
            'game_id' => $readyGame->id,
            'user_id' => null,
            'name' => 'Calm Raven',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 0,
        ]);
        // All players have submitted 3 topics each
        Topic::create(['game_id' => $readyGame->id, 'submitted_by_player_id' => $readyHost->id, 'text' => 'How does a dishwasher clean dishes?', 'is_used' => false]);
        Topic::create(['game_id' => $readyGame->id, 'submitted_by_player_id' => $readyHost->id, 'text' => 'How does a smoke detector sense fire?', 'is_used' => false]);
        Topic::create(['game_id' => $readyGame->id, 'submitted_by_player_id' => $readyHost->id, 'text' => 'How does a zipper work?', 'is_used' => false]);
        Topic::create(['game_id' => $readyGame->id, 'submitted_by_player_id' => $readyGuest1->id, 'text' => 'How does a suspension bridge support weight?', 'is_used' => false]);
        Topic::create(['game_id' => $readyGame->id, 'submitted_by_player_id' => $readyGuest1->id, 'text' => 'How does a thermos keep liquids hot or cold?', 'is_used' => false]);
        Topic::create(['game_id' => $readyGame->id, 'submitted_by_player_id' => $readyGuest1->id, 'text' => 'How does a ballpoint pen work?', 'is_used' => false]);
        Topic::create(['game_id' => $readyGame->id, 'submitted_by_player_id' => $readyGuest2->id, 'text' => 'How does a bicycle stay balanced?', 'is_used' => false]);
        Topic::create(['game_id' => $readyGame->id, 'submitted_by_player_id' => $readyGuest2->id, 'text' => 'How does a fire extinguisher put out flames?', 'is_used' => false]);
        Topic::create(['game_id' => $readyGame->id, 'submitted_by_player_id' => $readyGuest2->id, 'text' => 'How does a laser pointer produce light?', 'is_used' => false]);

        // --- Scenario 3: Playing phase — active player is choosing a topic ---
        // Log in as host-choosing@dev.test → should see play screen at /games/{code}/play with active player choosing
        $choosingCode = $this->uniqueCode();
        $choosingGame = Game::create([
            'host_user_id' => $hostChoosing->id,
            'code' => $choosingCode,
            'status' => 'playing',
            'current_round' => 1,
            'max_rounds' => 1,
            'state_updated_at' => now(),
        ]);
        $choosingHost = Player::create([
            'game_id' => $choosingGame->id,
            'user_id' => $hostChoosing->id,
            'name' => $hostChoosing->name,
            'is_host' => true,
            'has_submitted' => true,
            'score' => 0,
        ]);
        $choosingGuest1 = Player::create([
            'game_id' => $choosingGame->id,
            'user_id' => null,
            'name' => 'Jolly Panda',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 0,
        ]);
        $choosingGuest2 = Player::create([
            'game_id' => $choosingGame->id,
            'user_id' => null,
            'name' => 'Rapid Lynx',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 0,
        ]);
        // Topics for choosing game
        $chTopic1 = Topic::create(['game_id' => $choosingGame->id, 'submitted_by_player_id' => $choosingHost->id, 'text' => 'How does a helicopter hover?', 'is_used' => false]);
        $chTopic2 = Topic::create(['game_id' => $choosingGame->id, 'submitted_by_player_id' => $choosingHost->id, 'text' => 'How does a 3D printer build objects?', 'is_used' => false]);
        Topic::create(['game_id' => $choosingGame->id, 'submitted_by_player_id' => $choosingHost->id, 'text' => 'How does a wind turbine generate electricity?', 'is_used' => false]);
        Topic::create(['game_id' => $choosingGame->id, 'submitted_by_player_id' => $choosingGuest1->id, 'text' => 'How does noise-cancelling work in headphones?', 'is_used' => false]);
        Topic::create(['game_id' => $choosingGame->id, 'submitted_by_player_id' => $choosingGuest1->id, 'text' => 'How does an escalator move stairs?', 'is_used' => false]);
        Topic::create(['game_id' => $choosingGame->id, 'submitted_by_player_id' => $choosingGuest1->id, 'text' => 'How does a submarine dive and surface?', 'is_used' => false]);
        Topic::create(['game_id' => $choosingGame->id, 'submitted_by_player_id' => $choosingGuest2->id, 'text' => 'How does a solar panel convert sunlight to electricity?', 'is_used' => false]);
        Topic::create(['game_id' => $choosingGame->id, 'submitted_by_player_id' => $choosingGuest2->id, 'text' => 'How does a barcode scanner read prices?', 'is_used' => false]);
        Topic::create(['game_id' => $choosingGame->id, 'submitted_by_player_id' => $choosingGuest2->id, 'text' => 'How does a remote control send signals to a TV?', 'is_used' => false]);
        // First turn: choosing (active player sees topic selection buttons)
        Turn::create([
            'game_id' => $choosingGame->id,
            'player_id' => $choosingGuest1->id,
            'topic_id' => null,
            'topic_choices' => [$chTopic1->id, $chTopic2->id],
            'round_number' => 1,
            'turn_order' => 1,
            'status' => 'choosing',
            'started_at' => now()->subMinutes(1),
        ]);
        Turn::create([
            'game_id' => $choosingGame->id,
            'player_id' => $choosingGuest2->id,
            'topic_id' => null,
            'topic_choices' => [],
            'round_number' => 1,
            'turn_order' => 2,
            'status' => 'pending',
        ]);

        // --- Scenario 4: Grading complete — host can advance to next turn ---
        // Log in as host-grading-done@dev.test → should see results screen at /games/{code}/results/{turnId}
        $gradingDoneCode = $this->uniqueCode();
        $gradingDoneGame = Game::create([
            'host_user_id' => $hostGradingDone->id,
            'code' => $gradingDoneCode,
            'status' => 'grading_complete',
            'current_round' => 1,
            'max_rounds' => 1,
            'state_updated_at' => now(),
        ]);
        $gdHost = Player::create([
            'game_id' => $gradingDoneGame->id,
            'user_id' => $hostGradingDone->id,
            'name' => $hostGradingDone->name,
            'is_host' => true,
            'has_submitted' => true,
            'score' => 0,
        ]);
        $gdGuest1 = Player::create([
            'game_id' => $gradingDoneGame->id,
            'user_id' => null,
            'name' => 'Bold Otter',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 82,
        ]);
        $gdGuest2 = Player::create([
            'game_id' => $gradingDoneGame->id,
            'user_id' => null,
            'name' => 'Gentle Fox',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 0,
        ]);
        // Topics
        $gdTopic1 = Topic::create(['game_id' => $gradingDoneGame->id, 'submitted_by_player_id' => $gdHost->id, 'text' => 'How does a parachute slow your fall?', 'is_used' => true]);
        Topic::create(['game_id' => $gradingDoneGame->id, 'submitted_by_player_id' => $gdHost->id, 'text' => 'How does a magnifying glass start a fire?', 'is_used' => false]);
        Topic::create(['game_id' => $gradingDoneGame->id, 'submitted_by_player_id' => $gdHost->id, 'text' => 'How does a water filter purify water?', 'is_used' => false]);
        Topic::create(['game_id' => $gradingDoneGame->id, 'submitted_by_player_id' => $gdGuest1->id, 'text' => 'How does an MRI scan your body?', 'is_used' => false]);
        Topic::create(['game_id' => $gradingDoneGame->id, 'submitted_by_player_id' => $gdGuest1->id, 'text' => 'How does a catalytic converter reduce emissions?', 'is_used' => false]);
        Topic::create(['game_id' => $gradingDoneGame->id, 'submitted_by_player_id' => $gdGuest1->id, 'text' => 'How does a defibrillator restart a heart?', 'is_used' => false]);
        Topic::create(['game_id' => $gradingDoneGame->id, 'submitted_by_player_id' => $gdGuest2->id, 'text' => 'How does a pressure cooker cook faster?', 'is_used' => false]);
        Topic::create(['game_id' => $gradingDoneGame->id, 'submitted_by_player_id' => $gdGuest2->id, 'text' => 'How does a seatbelt save lives in a crash?', 'is_used' => false]);
        Topic::create(['game_id' => $gradingDoneGame->id, 'submitted_by_player_id' => $gdGuest2->id, 'text' => 'How does a thermostat know the temperature?', 'is_used' => false]);
        // First turn: complete (just graded), second turn: pending
        $gdCompletedTurn = Turn::create([
            'game_id' => $gradingDoneGame->id,
            'player_id' => $gdGuest1->id,
            'topic_id' => $gdTopic1->id,
            'round_number' => 1,
            'turn_order' => 1,
            'status' => 'complete',
            'transcript' => 'A parachute works by creating air resistance. The large fabric canopy catches air as you fall, which creates drag that opposes the force of gravity, slowing you down to a safe landing speed.',
            'score' => 82,
            'grade' => 'B',
            'feedback' => 'Good grasp of the basic physics. You correctly identified drag as the key mechanism. To improve, mention how the shape of the canopy creates a pocket of high-pressure air beneath it.',
            'actual_explanation' => 'A parachute slows descent through aerodynamic drag. When deployed, the large canopy fills with air, creating a high-pressure zone underneath. This air resistance (drag force) opposes gravity. The drag force increases with surface area and speed, so the large canopy creates enough drag to reduce terminal velocity from ~200 km/h to ~20 km/h, allowing a safe landing.',
            'started_at' => now()->subMinutes(8),
            'completed_at' => now()->subMinutes(3),
        ]);
        Turn::create([
            'game_id' => $gradingDoneGame->id,
            'player_id' => $gdGuest2->id,
            'topic_id' => null,
            'topic_choices' => [],
            'round_number' => 1,
            'turn_order' => 2,
            'status' => 'pending',
        ]);

        // --- Scenario 5: Round complete — host can start next round ---
        // Log in as host-round-done@dev.test → should see round complete screen at /games/{code}/round-complete
        $roundDoneCode = $this->uniqueCode();
        $roundDoneGame = Game::create([
            'host_user_id' => $hostRoundDone->id,
            'code' => $roundDoneCode,
            'status' => 'round_complete',
            'current_round' => 1,
            'max_rounds' => 2,
            'state_updated_at' => now(),
        ]);
        $rdHost = Player::create([
            'game_id' => $roundDoneGame->id,
            'user_id' => $hostRoundDone->id,
            'name' => $hostRoundDone->name,
            'is_host' => true,
            'has_submitted' => true,
            'score' => 0,
        ]);
        $rdGuest1 = Player::create([
            'game_id' => $roundDoneGame->id,
            'user_id' => null,
            'name' => 'Sneaky Raven',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 70,
        ]);
        $rdGuest2 = Player::create([
            'game_id' => $roundDoneGame->id,
            'user_id' => null,
            'name' => 'Calm Panda',
            'is_host' => false,
            'has_submitted' => true,
            'score' => 90,
        ]);
        // Topics
        $rdTopic1 = Topic::create(['game_id' => $roundDoneGame->id, 'submitted_by_player_id' => $rdHost->id, 'text' => 'How does a microphone convert sound to electricity?', 'is_used' => true]);
        $rdTopic2 = Topic::create(['game_id' => $roundDoneGame->id, 'submitted_by_player_id' => $rdHost->id, 'text' => 'How does a vacuum cleaner create suction?', 'is_used' => true]);
        Topic::create(['game_id' => $roundDoneGame->id, 'submitted_by_player_id' => $rdHost->id, 'text' => 'How does a light bulb produce light?', 'is_used' => false]);
        Topic::create(['game_id' => $roundDoneGame->id, 'submitted_by_player_id' => $rdGuest1->id, 'text' => 'How does an elevator know which floor to stop at?', 'is_used' => false]);
        Topic::create(['game_id' => $roundDoneGame->id, 'submitted_by_player_id' => $rdGuest1->id, 'text' => 'How does a blender crush ice?', 'is_used' => false]);
        Topic::create(['game_id' => $roundDoneGame->id, 'submitted_by_player_id' => $rdGuest1->id, 'text' => 'How does a traffic light change colors?', 'is_used' => false]);
        Topic::create(['game_id' => $roundDoneGame->id, 'submitted_by_player_id' => $rdGuest2->id, 'text' => 'How does a washing machine remove stains?', 'is_used' => false]);
        Topic::create(['game_id' => $roundDoneGame->id, 'submitted_by_player_id' => $rdGuest2->id, 'text' => 'How does a metal detector find buried objects?', 'is_used' => false]);
        Topic::create(['game_id' => $roundDoneGame->id, 'submitted_by_player_id' => $rdGuest2->id, 'text' => 'How does a water heater heat water?', 'is_used' => false]);
        // All turns in round 1 are complete
        Turn::create([
            'game_id' => $roundDoneGame->id,
            'player_id' => $rdGuest1->id,
            'topic_id' => $rdTopic1->id,
            'round_number' => 1,
            'turn_order' => 1,
            'status' => 'complete',
            'transcript' => 'A microphone has a thin diaphragm that vibrates when sound waves hit it. These vibrations move a coil near a magnet, which generates a small electrical current that matches the sound pattern.',
            'score' => 70,
            'grade' => 'C',
            'feedback' => 'You described the dynamic microphone type well but missed that there are different types (condenser, ribbon). The basic electromagnetic induction principle was correct.',
            'actual_explanation' => 'A microphone converts sound into electricity. In a dynamic mic, sound waves vibrate a diaphragm attached to a coil in a magnetic field, inducing an electrical signal via electromagnetic induction. In a condenser mic, sound vibrates one plate of a capacitor, changing the capacitance and producing a signal. The electrical output mirrors the original sound wave pattern.',
            'started_at' => now()->subMinutes(20),
            'completed_at' => now()->subMinutes(15),
        ]);
        Turn::create([
            'game_id' => $roundDoneGame->id,
            'player_id' => $rdGuest2->id,
            'topic_id' => $rdTopic2->id,
            'round_number' => 1,
            'turn_order' => 2,
            'status' => 'complete',
            'transcript' => 'A vacuum cleaner uses a motor to spin a fan that pushes air out of the machine. This creates a low-pressure area inside that sucks air and dirt in through the hose.',
            'score' => 90,
            'grade' => 'A',
            'feedback' => 'Excellent explanation of the pressure differential mechanism. Clear and accurate description of how the motor, fan, and pressure difference work together.',
            'actual_explanation' => 'A vacuum cleaner creates suction through pressure differential. An electric motor spins a fan (impeller) that pushes air out of the machine, creating a partial vacuum (low-pressure zone) inside. Atmospheric pressure outside is higher, so air rushes in through the intake, carrying dust and debris with it. The dirty air passes through filters that trap particles while clean air exits.',
            'started_at' => now()->subMinutes(13),
            'completed_at' => now()->subMinutes(8),
        ]);

        $this->command->info('');
        $this->command->info('Seeded games:');
        $this->command->table(
            ['Status', 'Code', 'Owner', 'Join/View URL'],
            [
                ['lobby', $lobbyCode, 'host-standard@dev.test', url("/dev/join-game/{$lobbyCode}")],
                ['submitting', $submittingCode, 'host-submitting@dev.test', url("/dev/login-as/{$hostSubmitting->id}")],
                ['submitting (all done)', $readyCode, 'host-ready@dev.test', url("/dev/login-as/{$hostReady->id}")],
                ['playing (choosing)', $choosingCode, 'host-choosing@dev.test', url("/dev/login-as/{$hostChoosing->id}")],
                ['playing', $playingCode, 'host-loaded@dev.test', url("/dev/join-game/{$playingCode}")],
                ['grading_complete', $gradingDoneCode, 'host-grading-done@dev.test', url("/dev/login-as/{$hostGradingDone->id}")],
                ['round_complete', $roundDoneCode, 'host-round-done@dev.test', url("/dev/login-as/{$hostRoundDone->id}")],
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
