## Codebase Patterns
- `now()->timestamp - $carbon->timestamp` is the reliable way to compute elapsed seconds in Laravel (avoid `diffInSeconds` which can return unexpected values in test environments)
- Laravel 12 + Vue/Inertia starter kit with Laravel Fortify for auth
- MySQL database in development (DB_CONNECTION=mysql, DB_DATABASE=how_does_that_work, root with no password)
- Tests use SQLite in-memory (phpunit.xml: DB_CONNECTION=sqlite, DB_DATABASE=:memory:)
- **MySQL FK naming bug**: Never chain `->index()` after `->constrained()` — overrides FK constraint name with boolean `true` → MySQL renders as `1` → "Duplicate foreign key constraint name '1'" error. MySQL auto-creates FK indexes.
- Pest PHP 4.4 for testing; Feature tests auto-get TestCase+RefreshDatabase via Pest.php config
- Unit tests needing DB must use `uses(Tests\TestCase::class, RefreshDatabase::class)` explicitly
- Migrations use date-based timestamps: `2026_02_24_000001_create_xxx_table.php`
- Run `./vendor/bin/pint` to auto-fix lint before commits; `./vendor/bin/pint --test` to check
- `composer test` runs lint + tests; both must pass before committing
- Vitest (v4) configured via `vitest.config.ts`; test script is `npm run test:js`; test files live alongside source at `resources/js/**/*.test.ts`
- Audio level detection: `calculateAudioLevel(Uint8Array)` returns RMS 0–1; `isSpeechDetected(level, threshold=0.01)` returns boolean; both in `resources/js/utils/audioLevel.ts`
- Model factories go in `database/factories/`; use `Model::factory()` pattern

---

## 2026-02-24 - US-001
- Implemented core database schema: games, players, topics, turns tables
- Created 4 migrations with all required columns, foreign keys, and indexes
- Created 4 Eloquent models (Game, Player, Topic, Turn) with full relationship methods
- Updated User model to add `games()` and `players()` hasMany relationships
- Created 4 model factories (GameFactory, PlayerFactory, TopicFactory, TurnFactory)
- Created 16 PEST unit tests across 4 test files verifying all model relationships
- Fixed pre-existing lint issues (single_blank_line_at_eof, unary_operator_spaces) in existing test files
- **Files changed:** 4 migrations, 4 models, 4 factories, 4 test files, User.php update
- **Learnings for future iterations:**
  - Unit tests in `tests/Unit/` that need the database must use `uses(Tests\TestCase::class, RefreshDatabase::class)` since Pest.php only auto-applies this to `tests/Feature/`
  - `nullOnDelete()` is the correct Eloquent migration method for nullable FKs (player.user_id, turn.topic_id)
  - The project had pre-existing lint issues (missing EOF newlines, operator spacing) that pint auto-fixed
  - PlayerFactory defaults `user_id` to null (guests), TopicFactory generates fake sentence text
  - TurnFactory sets topic_id to null by default since topic assignment happens in gameplay
---

## 2026-02-24 - US-022
- Switched default DB connection from sqlite to mysql in `config/database.php`
- Updated `.env.example` to uncomment and set MySQL variables (DB_HOST, DB_PORT=3306, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
- Updated `.env` to use local MySQL (how_does_that_work database, root, no password)
- `phpunit.xml` already used SQLite in-memory for tests — no change needed
- Fixed critical MySQL compatibility bug in all 4 custom migrations: removed `->index()` chained after `->constrained()`. This Fluent `__call` magic overrides the FK constraint `index` attribute (the constraint name string) with boolean `true`, which MySQL stringifies to `1`, causing "Duplicate foreign key constraint name '1'" on the second FK. MySQL auto-creates indexes on FK columns, so `->index()` was redundant.
- All migrations verified with `php artisan migrate:fresh` against MySQL 9.5.0
- All 57 PEST tests still pass (tests use SQLite in-memory, unaffected)
- **Files changed:** `config/database.php`, `.env.example`, `.env`, 4 migration files
- **Learnings for future iterations:**
  - MySQL 9.x requires globally unique FK constraint names per database
  - Chaining `->index()` on a `ForeignKeyDefinition` returned by `constrained()` is a Laravel footgun — it silently corrupts the constraint name via Fluent `__call` magic
  - MySQL auto-indexes FK columns, so explicit `->index()` after FK definition is unnecessary
  - `phpunit.xml` already properly isolates tests to SQLite in-memory — tests don't need MySQL
---

## 2026-02-24 - US-023
- Replaced the default Laravel starter kit Welcome.vue with a custom branded game homepage
- New page includes: game title, one-line tagline, "Host a Game" CTA, "Join a Game" form, and 4-step How to Play section
- "Host a Game" button: links to `/register` for guests (or `/dashboard` if authenticated), respects `canRegister` Fortify feature flag
- "Join a Game" form: text input + Join button that navigates to `/join/{code}` via `router.visit()` on submit/Enter
- Used existing Reka UI components: `Button` (with `asChild` + `Link` for navigation), `Input` (with `v-model`)
- Responsive layout with Tailwind — single column on mobile, two-column grid for How to Play on sm+
- **Files changed:** `resources/js/pages/Welcome.vue`
- **Learnings for future iterations:**
  - `Button as-child` + `<Link>` inside is the idiomatic Reka UI pattern for link-styled buttons (renders as `<a>` with button classes)
  - Inertia's `router.visit(url)` is the SPA-friendly way to navigate programmatically
  - Route helpers from `@/routes` return RouteDefinition objects (`{ url, method }`) — Inertia's `Link :href` accepts these objects directly via Wayfinder integration
  - Input component passes unknown attrs to the native `<input>` via Vue attribute inheritance — `maxlength`, `@keyup.enter`, etc. work without extra wiring
  - The `canRegister` prop (from Fortify Features) controls whether the Register link appears; always respect it for the Host button
---

## 2026-02-24 - US-024
- Added `credits` column (unsignedInteger, default 0) to users table via new migration
- Updated `User::$fillable` to include `credits`
- Created `DevSeeder` with 4 dev accounts (host-loaded/500, host-standard/100, host-broke/2, host-veteran/150), 3 games (lobby/playing/complete), and realistic topic/turn data
- Updated `DatabaseSeeder` to call `DevSeeder::class` only when `app()->environment('local')`
- Created `routes/dev.php` with GET /dev, /dev/login-as/{userId}, /dev/join-game/{code}
- Registered dev routes in `bootstrap/app.php` using `then:` callback, only when env=local
- Created `DevController` with index, loginAs, joinGame methods; loginAs/joinGame abort 404 in production as belt-and-suspenders
- Created `resources/views/dev/index.blade.php` (plain Blade, not Inertia) listing accounts, games, and available routes
- Seeder prints a formatted table summary to console (accounts + games with login/join URLs)
- **Files changed:** migration, User.php, DevSeeder.php, DatabaseSeeder.php, bootstrap/app.php, routes/dev.php, DevController.php, resources/views/dev/index.blade.php
- **Learnings for future iterations:**
  - `bootstrap/app.php` `withRouting()` accepts a `then:` closure for registering additional routes — use `Route::middleware('web')->group(base_path('routes/dev.php'))` inside it
  - Use `app()->environment('local')` not just `config('app.env') === 'local'` — it handles array matching cleanly
  - `User::updateOrCreate(['email' => ...], [...])` is idempotent — safe to re-seed without duplicates
  - Dev Blade views (not Inertia) are appropriate for dev tooling that doesn't need the full SPA
  - `$this->command` is available on Seeder when called via artisan; use `$this->command->info()` and `$this->command->table()` for formatted console output
  - The `credits` column is needed by US-024 before US-020 (billing) is implemented — added here as a prerequisite
---

## 2026-02-24 - US-002
- Added `Game::generateUniqueCode()` static method (loops until unique 6-char uppercase string found)
- Created `GameController` with `create()`, `store()`, and `lobby()` actions
- Added game routes to `routes/web.php` under `auth+verified` middleware: GET /games/create, POST /games, GET /games/{code}/lobby
- Created `resources/js/pages/games/Create.vue` — form with max_rounds radio (1 or 2), submits via Inertia `useForm`
- Created `resources/js/pages/games/Lobby.vue` — shows game code, QR code (generated via `qrcode` npm package in `onMounted`), shareable link, and player list
- Installed `qrcode` npm package (+ `@types/qrcode`) for client-side QR code generation
- 9 PEST feature tests in `tests/Feature/GameTest.php`
- **Files changed:** app/Models/Game.php, app/Http/Controllers/GameController.php, routes/web.php, resources/js/pages/games/Create.vue, resources/js/pages/games/Lobby.vue, tests/Feature/GameTest.php, package.json, package-lock.json
- **Learnings for future iterations:**
  - `resources/js/actions`, `resources/js/routes`, and `resources/js/wayfinder` are gitignored — they're Wayfinder auto-generated files, never commit them
  - For parameterized routes like `/games/{code}/lobby`, Wayfinder generates helpers with `(args: { code: string }, options?)` signature
  - `useForm` from `@inertiajs/vue3` is the idiomatic way to submit forms with Inertia — provides `form.post('/path')`, `form.errors`, `form.processing`
  - `QRCode.toDataURL(url, opts)` from the `qrcode` package returns a Promise<string> — call it in `onMounted` and bind the result to a `ref<string>`
  - The lobby page needs `game` and `joinUrl` props passed from the controller via `Inertia::render()`
  - `assertInertia` in PEST uses a closure: `$response->assertInertia(fn ($page) => $page->component('games/Lobby')->has('game')->has('joinUrl'))`
---

## 2026-02-24 - US-006
- Added `GameController::startGame()` — POST `/games/{code}/start-game` (auth + host only); validates game is in `submitting` status, transitions to `playing`, redirects to `/games/{code}/play`
- Updated `GameController::submissionStatus()` to include `players` array (name + has_submitted only, never topic text) for host's real-time progress view
- Updated `TopicController::show()` to pass `players` prop with submission status to the page (no topic text ever exposed)
- Rewrote `Submit.vue` host view: two-column layout with submission form (left) + player progress panel + "Start Game" button (right); "Start Game" always enabled; player list shows checkmark/pending indicators per player; polling now always starts for host (not just after host submits)
- Added 7 PEST feature tests in `tests/Feature/StartGameTest.php` covering: force-start (transitions to playing), force-start with zero submissions, non-host 403, unauthenticated 401, wrong-status error, submission-status includes player data, submit page passes players prop
- **Files changed:** `GameController.php`, `TopicController.php`, `routes/web.php`, `Submit.vue`, `StartGameTest.php` (new)
- **Learnings for future iterations:**
  - When adding player list data to API endpoints, always `->map()` to only expose `name` + `has_submitted` — never include topic text or IDs that could leak info
  - The dual-auth pattern (user session vs session-based guest) is in both GameController and TopicController — any new game-phase endpoint must implement the same pattern
  - `startForm = useForm({})` with `.post()` is the clean way to do a host-only action button (no payload needed, but CSRF + processing state managed automatically)
  - Host polling should always start on mount (not gated on `has_submitted`) so the player list is live from the moment the host loads the page

---

## 2026-02-24 - US-004
- Moved `GET /games/{code}/lobby` out of auth middleware group; lobby is now accessible to both authenticated hosts and session-based guest players
- Added `GET /games/{code}/players` polling endpoint returning JSON `{ players, nonHostCount }`
- Updated `GameController::lobby()` to determine `isHost` via player record (auth users) or session (guests), 403 if neither
- Added `GameController::players()` with same auth check, returns player list for long-polling
- Rewrote `Lobby.vue` with:
  - `isHost` prop: hosts see AppLayout with QR code, shareable link, and "Start Submission Phase" button (disabled until 2+ non-host players); guests see a simple page layout with "Waiting for host to start…"
  - Long polling via `setInterval` every 3s (cleared on unmount)
  - Live player list with green "online" indicator for both host and guest views
- Created 9 PEST feature tests in `tests/Feature/LobbyTest.php` covering access control and polling endpoint behavior
- **Files changed:** `routes/web.php`, `app/Http/Controllers/GameController.php`, `resources/js/pages/games/Lobby.vue`, `tests/Feature/LobbyTest.php`
- **Learnings for future iterations:**
  - Guest players need the lobby route outside auth middleware — use in-controller session checks instead
  - `$request->session()->get("player_id.{$code}")` is the canonical way to identify a guest player in a request
  - Polling endpoint uses the same dual-auth check pattern: if `$request->user()` → check player record; else → check session
  - Conditional layout (AppLayout for host, plain div for guest) works well with `v-if` in template; `NavUser` in AppSidebar reads `auth.user` which would crash for unauthenticated guests — keep guests out of AppLayout
  - `response()->json()` is already casting Eloquent models to arrays; no need to call `->toArray()` manually
  - Use `getJson()` in PEST (not `get()`) for API/JSON endpoints so the response is parsed as JSON
---

## 2026-02-24 - US-005
- Added `GameController::startSubmission()` — POST `/games/{code}/start-submission` (auth + host only); validates ≥2 non-host players, transitions game to `submitting`, redirects to submit page
- Updated `GameController::players()` to include `gameStatus` in JSON response so Lobby.vue can detect status change
- Added `GameController::submissionStatus()` — GET `/games/{code}/submission-status` (dual-auth); returns `submittedCount`, `totalCount`, `gameStatus` for polling
- Created `TopicController` with `show()` (GET `/games/{code}/submit`, dual-auth, redirects to lobby if not in submitting status) and `store()` (POST `/games/{code}/topics`, dual-auth, validates 3 topics at 5–120 chars, creates Topic records, sets `has_submitted = true`)
- Updated `Lobby.vue`: wired "Start Submission Phase" button to `useForm.post()`, added `gameStatus` check in pollPlayers to redirect to submit page when `submitting`
- Created `Submit.vue`: dual-view (AppLayout for host, plain div for guests), topic submission form (3 inputs, validation), waiting state with `submittedCount/totalCount` polling after submission
- Added 14 PEST feature tests covering: start submission, submit page access, topic creation, duplicate prevention, validation, status polling
- **Files changed:** `GameController.php`, `TopicController.php` (new), `routes/web.php`, `Lobby.vue`, `Submit.vue` (new), `TopicSubmissionTest.php` (new)
- **Learnings for future iterations:**
  - `resolvePlayer()` private method on TopicController encapsulates dual-auth (user vs session) cleanly — reuse this pattern for all game-phase controllers
  - Pint flags: `unary_operator_spaces` (spaces around `!`), `no_unused_imports`, `not_operator_with_successor_space` — run `./vendor/bin/pint` immediately after writing PHP
  - `useForm({})` from `@inertiajs/vue3` with `.post()` is the idiomatic way to trigger a form submission that also handles CSRF; even for empty-payload actions like "start submission"
  - `form.errors[\`topics.${n - 1}\`]` accesses dot-notated nested validation errors in Vue — Laravel uses `topics.0`, `topics.1` etc. for array field errors
  - Polling in Submit.vue only starts after submission (inside `if (player.has_submitted)` in `onMounted`) to avoid unnecessary requests
---

## 2026-02-24 - US-003
- Created `JoinController` with `show()` (GET /join/{code}) and `store()` (POST /join/{code}) actions
- `show()` renders `games/Join` Inertia page with `game`, `suggestedName` (random two-word animal combo), and `error` prop
- `store()` validates name, creates guest `Player` (user_id=null, is_host=false), stores `player_id` in session as `"player_id.{code}"`, redirects to lobby
- Error handling: returns `error` prop from show() for pre-load errors (game started, full); returns session errors from store() for post-submit errors
- Game code matching is case-insensitive (strtoupper'd before lookup)
- Max 10 players enforced on both show and store
- Created `resources/js/pages/games/Join.vue` using `AuthLayout` (appropriate for unauthenticated guests), `useForm` for submission
- Added two public routes to `routes/web.php` (no auth middleware): `GET /join/{code}`, `POST /join/{code}`
- 12 PEST feature tests in `tests/Feature/JoinTest.php`
- **Files changed:** `app/Http/Controllers/JoinController.php`, `routes/web.php`, `resources/js/pages/games/Join.vue`, `tests/Feature/JoinTest.php`
- **Learnings for future iterations:**
  - `$this->assertSessionHas()` is NOT available in PEST test closures — use `$response->assertSessionHas()` instead
  - Session key for player identity: `"player_id.{$game->code}"` — keyed by game code so one browser can track multiple games
  - Pint `single_quote` rule flags unnecessary double-quoted strings; run `./vendor/bin/pint` to auto-fix before testing
  - For guest-facing pages (no auth), use `AuthLayout` — it's a clean centered card layout without requiring login
  - Passing errors as Inertia props (for pre-load validation) is cleaner than session flash for read-only error states
---

## 2026-02-24 - US-007
- Added `topic_choices` JSON column to the `turns` table via new migration
- Updated `Turn` model: added `topic_choices` to `$fillable` and cast as `array`
- Created `app/Services/TurnAssignmentService` with `assignTurns(Game $game)` method:
  - Fetches all non-host players, shuffles them to determine turn order
  - Maintains an in-memory `claimedIds` set to prevent the same topic appearing in two turns' choices
  - For each round (1..max_rounds), for each player: picks up to 2 eligible topics (not submitted by that player, not already claimed), skips player if 0 eligible
  - Creates `Turn` records with `status=pending`, `topic_choices=[id1, id2]`
- Wired `TurnAssignmentService` into `GameController::startGame()` via dependency injection — called after game transitions to `playing`
- Created 7 PEST unit tests in `tests/Unit/Services/TurnAssignmentServiceTest.php`:
  - No turn assigns a player their own topic
  - No topic appears in two different turns
  - Algorithm works with 2, 5, and 10 players
  - Generates turns for both rounds when max_rounds=2
  - Skips player turn when 0 eligible topics remain
- **Files changed:** `database/migrations/2026_02_24_000006_add_topic_choices_to_turns_table.php`, `app/Models/Turn.php`, `app/Services/TurnAssignmentService.php`, `app/Http/Controllers/GameController.php`, `tests/Unit/Services/TurnAssignmentServiceTest.php`
- **Learnings for future iterations:**
  - `TurnAssignmentService` uses a Collection from memory (not re-querying DB per iteration) for efficiency — load topics once, filter in PHP
  - The `claimedIds` array grows during generation; `in_array()` is fine for game-scale data (max ~30 topics)
  - Laravel service classes are auto-resolved via the IoC container when type-hinted in controller method signatures — no manual binding needed
  - Topic "claiming" is in-memory only; `is_used` column stays false until a player actually chooses a topic during gameplay (US-008)
  - `tests/Unit/Services/` is a new directory — works fine; PEST discovers test files recursively
  - The `shuffle()` on a Collection returns a new shuffled Collection (doesn't modify in place)
---

## 2026-02-24 - US-008
- Modified `GameController::startGame()` to set the first turn (round 1, turn_order 1) to `choosing` status after `assignTurns()` runs
- Created `TurnController` with:
  - `show()` — GET `/games/{code}/play`: resolves current `choosing`/`recording` turn, returns Play.vue with `isActivePlayer` bool, topic choices (id + text)
  - `chooseTopic()` — POST `/games/{code}/turns/{turnId}/choose-topic`: validates topic is in turn's choices, sets `is_used = true` on chosen topic, transitions turn to `recording`
  - `playState()` — GET `/games/{code}/play-state`: JSON polling endpoint returning current turn status for non-active players
- Created `Play.vue`: host gets AppLayout; guest gets simple layout; active player sees two topic choice cards; non-active players see "X is choosing..." with 3s polling that redirects on state change
- Added 8 PEST feature tests in `tests/Feature/TopicChoiceTest.php`
- **Files changed:** `GameController.php`, `TurnController.php` (new), `routes/web.php`, `Play.vue` (new), `TopicChoiceTest.php` (new)
- **Learnings for future iterations:**
  - `has('prop', fn ($t) => ...)` in `assertInertia` is strict — use `->etc()` inside the closure if you don't assert every property of that object
  - `resolvePlayer()` dual-auth pattern (user vs session) is now in three controllers (TopicController, TurnController + future ones) — consider extracting to a trait or base controller if it grows further
  - When `startGame()` creates turns via `assignTurns()`, the first turn must be explicitly set to `choosing` — `assignTurns()` only creates `pending` turns by design
  - The `is_used` column on topics is only set to `true` when a player actively chooses a topic; unclaimed topic_choices remain `false` until chosen
---

## 2026-02-24 - US-009
- Updated `TurnController::playState()` to eagerly load the `player` relationship and include `chosenTopicText` and `chosenTopicPlayerName` in the JSON response when a turn is in `recording` status
- Updated `TurnController::show()` to include `chosen_topic_text` in the `currentTurn` Inertia prop (from a DB lookup when `topic_id` is set)
- Rewrote `Play.vue` with reactive local state (`localTurnStatus`, `revealPlayerName`, `revealTopicText`) and a 3-second countdown mechanism (`showCountdown`, `countdownSeconds`)
- Non-active players: when polling detects transition from `choosing` → `recording`, in-place countdown shows "{Player Name} has chosen to explain: {Topic Text} - Get Ready… N"
- Active player: when landing on `/games/{code}/play` with `recording` status, shows a mic test placeholder ("Mic check coming up…") for US-010 to implement fully
- Both host and guest views show the countdown panel when `showCountdown` is true
- Added 4 PEST feature tests in `tests/Feature/TopicRevealTest.php` covering: polling includes topic text on recording, polling returns null on choosing, Inertia page includes `chosen_topic_text`
- Fixed `TopicChoiceTest.php` assertion (added `->etc()`) to avoid strict property check failing on new `chosen_topic_text` field
- **Files changed:** `TurnController.php`, `Play.vue`, `TopicRevealTest.php` (new), `TopicChoiceTest.php` (fix)
- **Learnings for future iterations:**
  - `playState()` needed `->with('player')` added to the query to load the player relationship for `chosenTopicPlayerName` — without it, `$currentTurn->player` is null
  - In-place UI update (via reactive local state) is better UX than redirecting for non-active players detecting a state change — avoids full page reload
  - When adding new props to `currentTurn` in `show()`, always add `->etc()` to existing tests or update them to include the new field
  - `Topic::where('id', $id)->value('text')` is the efficient way to fetch a single column without loading the full model
---
## 2026-02-24 - US-010
- Set up Vitest (v4) for JavaScript unit testing: `vitest.config.ts` at project root, `test:js` script in package.json
- Created `resources/js/utils/audioLevel.ts` with two pure utility functions:
  - `calculateAudioLevel(dataArray: Uint8Array): number` — RMS of Web Audio API time-domain data (0=silence, ~1=max)
  - `isSpeechDetected(level: number, threshold = 0.01): boolean` — true when level exceeds threshold
- Created `resources/js/utils/audioLevel.test.ts` with 9 Vitest tests covering silence, max signal, half amplitude, and threshold detection
- Updated `Play.vue` mic test UI for active player in `recording` state:
  - `testing` phase: pulsing red dot + "Say 'testing, testing, one, two, three'..." instruction
  - Listens via `getUserMedia` + `AudioContext` + `AnalyserNode`, checks level every 100ms
  - Once speech detected for 1 cumulative second: shows "Mic confirmed!" + "Start My Turn" button
  - `error` state (mic permission denied): shows "Continue Without Mic Check" fallback button
  - "Start My Turn" stops mic stream; US-011 will wire up actual recording
- Updated non-active players and host views to show "{Name} is checking their microphone…" in `recording` state
- Added `watch()` on `props.currentTurn?.status` to handle Inertia SPA in-page transition from `choosing` → `recording` without full remount
- **Files changed:** `resources/js/utils/audioLevel.ts`, `resources/js/utils/audioLevel.test.ts`, `vitest.config.ts`, `package.json`, `resources/js/pages/games/Play.vue`
- **Learnings for future iterations:**
  - Vitest 4 works with `vitest.config.ts` using `defineConfig` from `vitest/config`; no need to modify `vite.config.ts`
  - For mic level detection, use `AudioContext` + `AnalyserNode.getByteTimeDomainData()` (NOT `MediaRecorder`); MediaRecorder is for capture/upload (US-011)
  - `Uint8Array` values 0–255 from `getByteTimeDomainData`: 128 = silence; normalize with `(value - 128) / 128` before RMS calculation
  - Inertia SPA navigations to same component update props reactively but do NOT remount; use `watch(props.xxx)` to detect and act on prop changes mid-session
  - `getUserMedia` requires user gesture or HTTPS; `micState === 'error'` fallback is important UX for permission-denied scenarios
  - For active player: `onMounted` handles the "page loaded with recording status" case; `watch` handles the "choosing → recording in-page transition" case
---

## 2026-02-24 - US-011
- Created `resources/js/utils/countdownTimer.ts`: `createCountdownTimer(duration, onTick, onDone)` — pure timer factory returning `{ start, stop }`, ticks every 1s, calls `onDone` at 0, idempotent start
- Created `resources/js/utils/countdownTimer.test.ts`: 8 Vitest tests with `vi.useFakeTimers()` covering tick, done, stop, no double-done, idempotent start
- Added `TurnController::startRecording()` — POST `/games/{code}/turns/{turnId}/start-recording`; sets `started_at = now()`, validates turn is in `recording` status, returns `started_at` ISO string
- Added `TurnController::storeAudio()` — POST `/api/games/{code}/turns/{turnId}/audio`; validates `audio` file, stores to `storage/app/audio/{code}/{turnId}.webm`, transitions turn to `grading`, sets `completed_at`
- Updated `TurnController::playState()` to return `recordingStarted` (bool) and `timeRemaining` (int|null) based on `started_at`
- Added two new routes to `routes/web.php`
- Rewrote active player recording UI in `Play.vue`: full `MediaRecorder` capture flow, 2-minute countdown (turns red at ≤30s), "I'm Done" button, uploading state, grading state, retry on error
- Non-active players see "{Name} is explaining… (M:SS)" with local countdown synced from polling `timeRemaining`; host view also updated with explaining panel
- Added 9 PEST feature tests in `RecordingTest.php` covering: startRecording (success, 403, 422), storeAudio (success, 403, 422, missing file), playState fields
- **Files changed:** `TurnController.php`, `routes/web.php`, `Play.vue`, `countdownTimer.ts`, `countdownTimer.test.ts`, `RecordingTest.php`
- **Learnings for future iterations:**
  - `Carbon::diffInSeconds()` can return unexpected values in SQLite test environment — use `now()->timestamp - $carbon->timestamp` for reliable elapsed-time math
  - `MediaRecorder` `ondataavailable` fires when `.stop()` is called; use a Promise wrapping `onstop` to await final chunk collection before blob creation
  - PEST global helper functions must have unique names across all test files (all are loaded into the same namespace); name collisions cause "Cannot redeclare function" fatal error
  - For POST endpoints that return JSON (not Inertia redirects), use `postJson()` in PEST tests; `post()` follows redirects and doesn't parse JSON
  - `Storage::fake('local')` + `UploadedFile::fake()->create(name, kb, mime)` is the clean pattern for testing file uploads in PEST
  - Audio file upload uses `storeAs()` directly on the `UploadedFile` object — no need to import `Illuminate\Support\Facades\Storage` in the controller
---

## 2026-02-24 - US-012
- Installed `openai-php/laravel` package and published `config/openai.php`
- Fixed `OPEN_AI_API_KEY` → `OPENAI_API_KEY` in `.env` and `.env.example` (config expects `OPENAI_API_KEY`)
- Added `grading_failed` to `turns.status` enum via new migration `2026_02_24_000007_add_grading_failed_to_turns_status.php`
- Created `app/Jobs/TranscribeAudio.php`: reads audio from storage, calls `OpenAI::audio()->transcribe()` with `whisper-1`, stores transcript, dispatches `GradeTurn` on success, sets `grading_failed` on error/empty
- Created stub `app/Jobs/GradeTurn.php` (empty `handle()` — implemented in US-013)
- Updated `TurnController::storeAudio()` to `dispatch(new TranscribeAudio($turn))` after storing audio
- Added `Queue::fake()` to `RecordingTest` audio-upload test to prevent synchronous job execution in tests
- Created 5 PEST tests in `TranscribeAudioTest.php`: upload dispatches job, transcript stored + GradeTurn dispatched, empty transcript → grading_failed, exception → grading_failed, missing file → grading_failed
- **Files changed:** `composer.json`, `composer.lock`, `config/openai.php`, `app/Jobs/TranscribeAudio.php`, `app/Jobs/GradeTurn.php`, `app/Http/Controllers/TurnController.php`, `database/migrations/2026_02_24_000007_add_grading_failed_to_turns_status.php`, `tests/Feature/TranscribeAudioTest.php`, `tests/Feature/RecordingTest.php`, `.env.example`
- **Learnings for future iterations:**
  - `QUEUE_CONNECTION=sync` in `phpunit.xml` means dispatched jobs run immediately in tests — always add `Queue::fake()` when testing HTTP endpoints that dispatch jobs, or the job will execute and mutate state
  - `OpenAI::fake([TranscriptionResponse::fake(['text' => '...'])])` is the clean test pattern; `TranscriptionResponse` uses `Fakeable` trait with a fixture; override just the `text` key
  - `openai-php/laravel` facade is `OpenAI\Laravel\Facades\OpenAI` (not `OpenAI\Facades\OpenAI`) — use the Laravel-specific namespace
  - SQLite supports `ALTER TABLE ... CHANGE` via doctrine/dbal — the `grading_failed` enum migration works in both MySQL (production) and SQLite (tests)
  - `Storage::disk('local')->readStream($path)` is the way to get a file stream for OpenAI's audio transcribe API; it expects a resource/stream, not a file path string
---

## 2026-02-24 - US-013
- Implemented `GradeTurn` job: calls `gpt-4o-mini` with a structured prompt (topic + transcript), parses JSON response, stores all grading fields, increments player score, sets turn to `complete` and game to `grading_complete`
- Added retry logic: `public int $tries = 3` + throws `RuntimeException` on malformed/missing JSON keys; `failed()` sets turn status to `grading_failed`
- Added `response_format: json_object` to GPT call for more reliable JSON output
- Created migration `2026_02_24_000008_add_grading_complete_to_games_status.php`: adds `grading_complete` and `round_complete` to games.status enum (round_complete needed by US-015/016)
- Score is clamped to 0–100 range in case GPT returns out-of-range values
- 8 PEST feature tests in `GradeTurnTest.php` covering: all fields stored, player score incremented, increments on top of existing score, game status set, malformed JSON throws, missing keys throws, failed() sets grading_failed, score clamping
- **Files changed:** `app/Jobs/GradeTurn.php`, `database/migrations/2026_02_24_000008_add_grading_complete_to_games_status.php`, `tests/Feature/GradeTurnTest.php`
- **Learnings for future iterations:**
  - `CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => '...'], 'finish_reason' => 'stop']]])` is the correct pattern for faking GPT chat responses; must include `role` in the message object
  - Helper function names in PEST test files must be globally unique — `makeGradingTurn` was taken by `TranscribeAudioTest.php`, so use `buildGradeTurnFixture` etc.
  - `$turn->fresh()->load('player', 'topic', 'game')` is the pattern to reload a turn with all needed relationships at the start of a job handle (stale model from constructor)
  - `$turn->player->increment('score', $score)` is the atomic increment pattern — avoids race conditions vs `$player->score += $score; $player->save()`
  - Adding `round_complete` to the games enum now saves another migration later (US-015/016 need it)
---

## 2026-02-24 - US-014
- Created `TurnController::results()` action (GET `/games/{code}/results/{turnId}`): returns `games/Results` Inertia page with turn data (player name, topic, grade, score, feedback, actual_explanation), scoreboard (players sorted by score desc), and isHost flag
- Updated `TurnController::playState()` to include `completedTurnId` in JSON when `game.status === 'grading_complete'` (finds latest `complete` turn by `updated_at desc`)
- Added route `GET /games/{code}/results/{turnId}` → `TurnController::results` to `routes/web.php`
- Added dev helper route `GET /dev/completed-turn` → `DevController::completedTurn`: logs in as `host-veteran@dev.test` and redirects to the first completed turn's results page for Playwright testing
- Created `resources/js/pages/games/Results.vue`:
  - Host view (AppLayout): shows turn results with grade badge (A=green/B=blue/C=yellow/D=orange/F=red), score, feedback, actual explanation, scoreboard, and "Next Player →" button
  - Non-host view: same results but shows "Waiting for host to continue…" instead of the button
  - Grading failed fallback: "Grading failed — no score awarded" when `status === 'grading_failed'` or grade is null
- Updated `Play.vue` polling: detects `gameStatus === 'grading_complete'` with a `completedTurnId` → navigates to results via `window.location.href`
- Active player now starts polling after successful audio upload (when `recordingPhase = 'done'`) so they also navigate to results when grading completes
- 7 PEST feature tests in `ResultsTest.php`: host/guest access, score sorting, grading_failed, auth check, play-state completedTurnId presence
- Playwright E2E test in `tests/e2e/results.spec.ts` using `/dev/completed-turn` helper route
- **Files changed:** `TurnController.php`, `DevController.php`, `routes/web.php`, `routes/dev.php`, `resources/js/pages/games/Results.vue`, `resources/js/pages/games/Play.vue`, `tests/Feature/ResultsTest.php`, `tests/e2e/results.spec.ts`
- **Learnings for future iterations:**
  - `playState()` needed a new `completedTurnId` field: query `turns` for the most recently updated `complete` turn when `game.status === 'grading_complete'`
  - Active player skips polling in `onMounted` (returns early) — must explicitly start `pollInterval` after audio upload so they can navigate to results
  - Dev helper `/dev/completed-turn` pattern (log in + redirect to a specific resource) is very useful for Playwright tests that need to bypass auth and find a known resource
  - Results page uses `window.location.href` (full page load) rather than Inertia `router.visit()` to avoid issues with the polling interval + component lifecycle
  - `computed` for grade badge CSS class is the clean way to handle conditional styling for 5 grade values
---

## 2026-02-24 - US-015
- Added `TurnController::advance()` — POST `/games/{code}/advance` (host only): finds next `pending` turn, sets it to `choosing`, game back to `playing`; if no pending turns, transitions game to `round_complete`; always redirects to `/games/{code}/play`
- Added route `POST /games/{code}/advance` to `routes/web.php`
- Updated `Results.vue`: non-host players now poll `/games/{code}/play-state` every 3 seconds; when `gameStatus` is `playing` or `round_complete`, navigate via `window.location.href` to `/games/{code}/play`
- Added 6 PEST feature tests in `AdvanceTurnTest.php`: host advances to next turn, round_complete when no pending turns, non-host 403, guest session 403, unauthenticated 403, only lowest turn_order is set to choosing
- **Files changed:** `TurnController.php`, `routes/web.php`, `resources/js/pages/games/Results.vue`, `tests/Feature/AdvanceTurnTest.php`
- **Learnings for future iterations:**
  - `advance()` uses `resolvePlayer()` and checks the second return value (`$isHost`) for host-only enforcement — guests always return `false` for isHost so no extra check needed
  - After `round_complete`, `TurnController::show()` redirects to lobby (since game status is not `playing`) — US-016 will need to add a round complete screen/route before changing this redirect logic
  - Non-host polling on Results.vue is gated by `!props.player.is_host` — hosts navigate via Inertia form post redirect, not polling
  - `state_updated_at` should always be updated in any state-transition endpoint so that polling clients can detect the change
---

## 2026-02-24 - US-016
- Fixed `TurnController::advance()` to scope pending turn search to `$game->current_round` only (prevents pre-generated round 2 turns from being activated instead of triggering round_complete)
- Changed `advance()` redirect to `/games/{code}/round-complete` when transitioning to `round_complete`
- Updated `TurnController::show()` to redirect to `/games/{code}/round-complete` if game status is `round_complete`
- Added `TurnController::roundComplete()` — GET `/games/{code}/round-complete`: returns RoundComplete Inertia page with game, player, players (sorted by score), roundTurns (complete turns for current round with grade/score)
- Added `TurnController::startNextRound()` — POST `/games/{code}/start-next-round`: host-only; increments current_round, activates first pending turn for next round, sets game to playing
- Added `TurnController::finalizeGame()` — POST `/games/{code}/finalize`: host-only; sets game to 'complete', redirects to `/games/{code}/complete` (stub for US-017)
- Created `resources/js/pages/games/RoundComplete.vue`: host view (AppLayout) + guest view; shows round header, grade history for current round, full scoreboard; host sees "Start Round N" button or "View Final Results" button; non-host polls for game transition
- Updated `Results.vue` polling: navigates to `/games/{code}/round-complete` when `gameStatus === 'round_complete'` (was incorrectly navigating to /play)
- Added 3 new routes: GET round-complete, POST start-next-round, POST finalize
- Updated AdvanceTurnTest.php: changed round_complete redirect assertion from `/play` to `/round-complete`
- 10 PEST feature tests in `RoundCompleteTest.php` covering: host/guest access, scoreboard sorting, grade history, max_rounds prop, start-next-round, access control, and the full round_complete transition
- **Files changed:** `TurnController.php`, `routes/web.php`, `RoundComplete.vue` (new), `Results.vue`, `RoundCompleteTest.php` (new), `AdvanceTurnTest.php`
- **Learnings for future iterations:**
  - `advance()` must scope pending turn search to `current_round` — without this, pre-generated round 2 turns would be activated prematurely in multi-round games
  - `TurnAssignmentService` pre-generates turns for ALL rounds upfront; "Start Round N" just activates the first pending turn for that round and increments `game.current_round`
  - Results.vue polling was checking for both `playing` AND `round_complete` to navigate to `/play` — this was wrong; `round_complete` should navigate to `/round-complete`
  - The `finalizeGame()` stub redirects to `/games/{code}/complete` which US-017 will implement; the game status transitions to 'complete' so US-017 can check for it
---

## 2026-02-24 - US-017
- Added `TurnController::complete()` — GET `/games/{code}/complete`: returns `games/Complete` Inertia page with players sorted by score, winner info, and all completed turns per player
- Added `TurnController::playAgain()` — POST `/games/{code}/play-again`: host-only; creates new `Game` in `lobby` status with same `max_rounds`, adds host as player, redirects to new game's lobby
- Updated `TurnController::show()` to redirect to `/games/{code}/complete` when game status is `complete`
- Added two routes: GET `/games/{code}/complete` and POST `/games/{code}/play-again`
- Created `resources/js/pages/games/Complete.vue`: winner banner (trophy + "{Name} wins with {score} points!"), final scores ranked list (winner highlighted in gold), turn history per player with grade badges, "Play Again" button for host; guest view shows same info with "Thanks for playing!" footer instead
- Added `DevController::completedGame()` dev helper: logs in as `host-veteran@dev.test` and redirects to `/games/{code}/complete`
- Added `GET /dev/completed-game` route to `routes/dev.php`
- 9 PEST feature tests in `GameCompleteTest.php` covering: host/guest access, score sorting, turn count, auth guard, play redirect, play-again creates new game, access control
- 3 Playwright E2E tests in `game-complete.spec.ts` covering: winner banner, player ranking, turn history
- **Files changed:** `TurnController.php`, `DevController.php`, `routes/web.php`, `routes/dev.php`, `Complete.vue` (new), `GameCompleteTest.php` (new), `game-complete.spec.ts` (new)
- **Learnings for future iterations:**
  - `complete` game status check should come before `round_complete` check in `show()` redirects
  - `playAgain()` requires `$request->user()` (not just `$isHost`) because guests who are hosts of completed games can't create new auth games — only authenticated hosts can
  - `turnsForPlayer(playerId)` as a computed function in Vue template is cleaner than pre-grouping on the server side for this display pattern
  - Dev route `/dev/completed-game` follows the same pattern as `/dev/completed-turn` — log in as known dev user, find their completed game, redirect
---

## 2026-02-24 - US-018
- Added `TurnController::gameState()` — GET `/api/games/{code}/state`: consolidated state endpoint returning `game.status`, `game.current_round`, `current_turn` (id, player_name, topic, status, time_remaining), `players` (id, name, score, has_submitted), and `last_updated` ISO timestamp
- Added `TurnController::buildGameState()` private helper: builds the state array from a Game model, finds the active choosing/recording turn, computes time_remaining from started_at, maps players
- Caching: 1-second `Cache::remember` keyed by `game_state_{code}_{state_updated_at_timestamp}` — same state = cache hit; any state change = new key = fresh response
- Added route `GET /api/games/{code}/state` to `routes/web.php`
- Created `resources/js/composables/useGameState.ts`: factory function `useGameState(code, onStateChange?)` returning `{ state, start, stop }` — polls `/api/games/{code}/state` every 3 seconds, compares `last_updated`, calls `onStateChange` on change, ignores network errors
- Created `resources/js/composables/useGameState.test.ts`: 8 Vitest tests covering immediate call, 3s interval, state-change detection, no-change suppression, stop(), state ref update, error/non-ok handling
- 13 PEST feature tests in `GameStateTest.php` covering: auth/session access control, correct JSON shape for all game statuses (lobby/submitting/playing/grading_complete/round_complete/complete), choosing/recording turn in current_turn, time_remaining calculation, last_updated value
- **Files changed:** `TurnController.php`, `routes/web.php`, `useGameState.ts` (new), `useGameState.test.ts` (new), `GameStateTest.php` (new)
- **Learnings for future iterations:**
  - Cache key with `state_updated_at` timestamp avoids stale test data: `game_state_{code}_{timestamp}` — cache is effectively invalidated when game state changes without needing `Cache::flush()` in tests
  - `await Promise.resolve()` only flushes ONE microtask tick — for async chains with multiple `await` (e.g., `await fetch()` then `await json()`), use a `flushPromises()` helper that calls `await Promise.resolve()` 3 times
  - `vi.stubGlobal('fetch', mockFn)` + `vi.restoreAllMocks()` in afterEach is the clean pattern for mocking global `fetch` in Vitest
  - The composable uses no Vue lifecycle hooks (no `onMounted`/`onUnmounted`) so it can be tested without Vue context — consumer calls `start()`/`stop()` in their lifecycle hooks
  - `Cache::flush()` in `beforeEach` in PEST tests ensures the 1-second cache doesn't interfere between test cases that share game codes (rare but safe)
---

## 2026-02-24 - US-019
- Fortify registration (`/register`) and redirect to `/dashboard` were already configured (`'home' => '/dashboard'` in `config/fortify.php`) — no changes needed for registration itself
- Created `DashboardController::index()`: queries auth user's games with players eagerly loaded, maps to summary array (code, status, created_at, player_count, winner)
- Updated dashboard route in `routes/web.php` to use `DashboardController::index` instead of a closure
- Rewrote `Dashboard.vue`: replaced placeholder patterns with a real games table (date, code, players, winner, status badge); empty state with "Host a Game" CTA; status badge color-coded (complete=green, in-progress=blue, lobby=gray)
- Added 5 PEST feature tests in `DashboardTest.php`: guests redirect, authenticated can view, shows host's games, filters by host, shows winner, orders newest first
- Added 6 PEST feature tests in `RegistrationTest.php`: form renders, registers and redirects to dashboard, creates user record, validates name/email/password
- **Files changed:** `app/Http/Controllers/DashboardController.php` (new), `routes/web.php`, `resources/js/pages/Dashboard.vue`, `tests/Feature/DashboardTest.php`, `tests/Feature/RegistrationTest.php` (new)
- **Learnings for future iterations:**
  - Fortify `'home' => '/dashboard'` in `config/fortify.php` controls the post-auth redirect — no extra controller work needed for registration redirect
  - `$user->games()->with('players')->orderByDesc('created_at')->get()` is the clean pattern for fetching hosted games with eager-loaded players
  - `$game->players->sortByDesc('score')->first()` on an already-loaded Collection avoids N+1 queries for winner determination
  - The existing `tests/Feature/Auth/RegistrationTest.php` (from Fortify starter kit) only tests basic registration — our new `RegistrationTest.php` adds dashboard-redirect and validation tests
  - Pint flags `no_unused_imports` in test files too — don't import `use App\Models\User` unless you actually reference it by name
---

## 2026-02-24 - US-020
- Installed `laravel/cashier` (v16) and published config/migrations
- Added `Billable` trait to `User` model
- Created `BillingController` with `index()` (billing page), `checkout()` (Stripe Checkout via `checkoutCharge()`), and `success()` actions
- Created `StripeWebhookController` with custom `handle()` that verifies Stripe signature when `STRIPE_WEBHOOK_SECRET` is set; skips verification in test env (secret not set in phpunit.xml)
- Added CSRF exemption for `stripe/webhook` route in `bootstrap/app.php` via `$middleware->validateCsrfTokens(except: ['stripe/webhook'])`
- Created `Billing.vue` and `BillingSuccess.vue` pages using AppLayout
- Updated `TopicController::show()` to pass `hostCredits` prop (null for guests, int for hosts)
- Updated `Submit.vue` to disable "Start Game" button and show a billing link when `hostCredits <= 0`
- Updated `GameController::startGame()` to check credits server-side before starting (returns error if 0)
- Updated `GradeTurn` and `TranscribeAudio` jobs to deduct 1 credit from the host after each successful API call; never goes below 0
- Added `credits => 100` default to `UserFactory` so test users have credits by default (fixes downstream tests that expect startGame to succeed)
- Added Stripe keys (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`) to `.env.example`
- 13 PEST tests in `BillingTest.php` covering: billing page auth, start-game credit check, hostCredits prop, webhook increment, webhook edge cases, signature check, credit deduction in GradeTurn
- **Files changed:** `composer.json/lock`, `config/cashier.php`, 5 Cashier migrations, `User.php`, `GameController.php`, `TopicController.php`, `GradeTurn.php`, `TranscribeAudio.php`, `bootstrap/app.php`, `routes/web.php`, `Submit.vue`, `UserFactory.php`, `BillingController.php`, `StripeWebhookController.php`, `Billing.vue`, `BillingSuccess.vue`, `BillingTest.php`
- **Learnings for future iterations:**
  - `$middleware->validateCsrfTokens(except: ['stripe/webhook'])` is the Laravel 11 way to exempt a route from CSRF — goes in `bootstrap/app.php`'s `withMiddleware()` closure
  - Stripe webhook signature verification: when `STRIPE_WEBHOOK_SECRET` is empty/null (as in phpunit.xml), skip `Webhook::constructEvent()` and parse the payload directly — this makes webhooks testable without real Stripe
  - `user->checkoutCharge($amountCents, $name, $qty, $options)` is the Cashier method for inline one-time Stripe Checkout (no pre-configured Price ID needed); pass `client_reference_id` to identify the user in the webhook
  - `UserFactory` defaults should reflect a "happy path" test user (credits: 100) — tests that specifically need 0 credits should set it explicitly with `User::factory()->create(['credits' => 0])`
  - Credit deduction in jobs: use `if ($host && $host->credits > 0) { $host->decrement('credits'); }` — guard prevents going below zero
  - Cashier v16 migrations: `cashier:install` command doesn't exist; use `php artisan vendor:publish --tag="cashier-migrations"` to publish them
---

## 2026-02-24 - US-021
- Implemented API usage tracking per game
- Created `api_usage_logs` migration with `game_id`, `user_id`, `type` (whisper|gpt), `tokens_used` (nullable), `cost_credits`, `timestamps`
- Created `ApiUsageLog` model with `game()` and `user()` relationships
- Updated `TranscribeAudio` job: checks host credits BEFORE calling Whisper; if 0 credits, marks turn `grading_failed` with "Host ran out of credits." message; on success deducts 1 credit and creates a `whisper` ApiUsageLog record
- Updated `GradeTurn` job: checks host credits BEFORE calling GPT; if 0 credits, marks turn `grading_failed` and advances game to `grading_complete`; on success deducts 1 credit and creates a `gpt` ApiUsageLog record
- Files changed: `database/migrations/2026_02_24_000009_create_api_usage_logs_table.php`, `app/Models/ApiUsageLog.php`, `app/Jobs/TranscribeAudio.php`, `app/Jobs/GradeTurn.php`, `tests/Feature/ApiUsageTest.php`
- **Learnings for future iterations:**
  - When jobs need to skip API calls based on resource availability, check BEFORE the API call (not after)
  - `$response->usage?->totalTokens` safely accesses token count from OpenAI chat responses (null if no usage data)
  - pint `no_unused_imports` will remove model imports if the model class is only referenced via string table names (e.g. `assertDatabaseHas('api_usage_logs', ...)` doesn't require `use App\Models\ApiUsageLog`)
  - All existing billing tests still pass because the zero-credits guard only skips the API call — credits stay at 0, which is what those tests assert
---

## 2026-02-24 - Deploy US-001
- Merged chief/how-does-that-work branch into main (fast-forward)
- Remote origin/main was already up to date with all 52 commits
- Local main fast-forwarded to match; branches are now identical
- No files changed (this was a git branch operation, not code changes)
- **Learnings for future iterations:**
  - The remote main was already pushed at some point, so only the local ref needed updating
  - `git merge --ff-only origin/main` is the safe way to catch up a local branch without creating merge commits
  - The `.chief/` directory is gitignored, so PRD changes don't need to be committed
---
