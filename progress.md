## Codebase Patterns
- Laravel 12 + Vue/Inertia starter kit with Laravel Fortify for auth
- MySQL database in development (DB_CONNECTION=mysql, DB_DATABASE=how_does_that_work, root with no password)
- Tests use SQLite in-memory (phpunit.xml: DB_CONNECTION=sqlite, DB_DATABASE=:memory:)
- **MySQL FK naming bug**: Never chain `->index()` after `->constrained()` — overrides FK constraint name with boolean `true` → MySQL renders as `1` → "Duplicate foreign key constraint name '1'" error. MySQL auto-creates FK indexes.
- Pest PHP 4.4 for testing; Feature tests auto-get TestCase+RefreshDatabase via Pest.php config
- Unit tests needing DB must use `uses(Tests\TestCase::class, RefreshDatabase::class)` explicitly
- Migrations use date-based timestamps: `2026_02_24_000001_create_xxx_table.php`
- Run `./vendor/bin/pint` to auto-fix lint before commits; `./vendor/bin/pint --test` to check
- `composer test` runs lint + tests; both must pass before committing
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
