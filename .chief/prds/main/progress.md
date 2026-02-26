## Codebase Patterns
- Laravel 12 + Vue 3 (Inertia.js) stack
- Player model uses `fillable` array (not guarded)
- Guest players identified via session key `player_id.{CODE}`
- Host players identified via `$request->user()` (authenticated)
- Use `Illuminate\Support\Str::uuid()->toString()` for UUID generation
- PHP lint: `composer test:lint` (runs Pint), PHP tests: `composer test`
- JS lint: `npm run lint` (ESLint), JS format: `npm run format` (Prettier)
- Pre-existing test failures in BillingTest (Stripe webhook signature) — not related to game logic
- Pre-existing ESLint errors for playwright.config.ts, vitest.config.ts, e2e test files (not in tsconfig)
- Migrations use `php artisan make:migration` naming convention
- Inertia.js reuses component instances on same-page redirects — `onMounted` does NOT re-fire when props update via `form.post()` redirect. Use `watch` on reactive props for post-submission logic.
- All polling endpoints (players, submission-status, play-state) use consistent session-based guest auth via `player_id.{CODE}` — no auth issues for guests
- DevController queries use `LIKE 'host-%@dev.test'` to auto-discover all dev users — new scenario users are automatically listed on the dashboard
- DevSeeder scenario users follow naming convention: `host-{scenario}@dev.test` (e.g., `host-submitting@dev.test`, `host-ready@dev.test`)
- Polling in all game pages uses raw `setInterval` (no composable) with 3-second intervals
- Game page routes (GET Inertia pages) are wrapped in `RedirectToGameState` middleware; polling/API/POST routes are NOT
- Game status → correct URL mapping is in `RedirectToGameState::correctUrlForStatus()` — use this as the source of truth for redirect rules
- `RedirectToGameState::correctUrlForStatus()` is `public static` — reusable from any controller/endpoint
- To pass data from a POST redirect to the destination page, use Laravel session flash + Inertia shared props via `HandleInertiaRequests::share()` — access it as `usePage().props.flash.key`
- CSRF token for fetch() requests: read from `XSRF-TOKEN` cookie, decode with `decodeURIComponent`, send in `X-XSRF-TOKEN` header
- E2E tests: `npm run test:e2e` runs Playwright; global setup seeds DB once; shared helpers in `tests/e2e/helpers.ts` (`loginAs`, `expectUrl`)
- E2E tests use dev routes (`/dev/login-as/{userId}`, `/dev/completed-turn`, `/dev/completed-game`) for setup — no UI simulation needed
- Playwright browsers need separate install: `npx playwright install chromium`
---

## 2026-02-26 - US-001
- What was implemented: Added `reconnect_token` (nullable UUID) column to `players` table
- Files changed:
  - `database/migrations/2026_02_26_221146_add_reconnect_token_to_players_table.php` (new)
  - `app/Models/Player.php` (added `reconnect_token` to fillable)
  - `app/Http/Controllers/JoinController.php` (generate UUID on player creation)
- **Learnings for future iterations:**
  - Player model uses a simple `$fillable` array — add new columns there
  - `JoinController::store()` is where guest players are created — any player creation logic goes here
  - The `PlayerFactory` does NOT need to include `reconnect_token` since it's nullable and defaults to null
  - There are many unrelated modified files in the working tree (Vue components) — be careful to only stage relevant files when committing
---

## 2026-02-26 - US-002
- What was implemented: Fixed long polling bug where guest players never started polling after submitting topics
- **Root cause:** In `Submit.vue`, the polling condition (`props.player.is_host || props.player.has_submitted`) was only evaluated in `onMounted`. When a guest submitted topics via `form.post()`, Inertia redirected back to the same page with updated props (`has_submitted: true`), but since Inertia reuses the component instance, `onMounted` didn't re-fire. Polling never started for the guest.
- **Fix:** Added a `watch` on `props.player.has_submitted` that starts polling when it transitions to `true` after form submission.
- Files changed:
  - `resources/js/pages/games/Submit.vue` (added `watch` import and watcher for `has_submitted` prop)
- **Learnings for future iterations:**
  - This is a common Inertia.js pitfall: `form.post()` redirects back to the same page, but the component instance is reused (not remounted). Always use `watch` instead of relying solely on `onMounted` for props that change via Inertia navigation.
  - Server-side polling endpoints all return `JsonResponse` directly — no header-dependent behavior for `Accept` or `X-Requested-With`
  - Play.vue, Results.vue, and RoundComplete.vue polling logic is correct — non-active/non-host players always poll in `onMounted`
  - The `TopicController::store()` redirects to `/games/{code}/submit` after submission (line 89)
---

## 2026-02-26 - US-003
- What was implemented: Centralized game state redirect middleware (`RedirectToGameState`) that intercepts game page requests and redirects players to the correct URL based on current game status
- Files changed:
  - `app/Http/Middleware/RedirectToGameState.php` (new) — middleware with redirect rules for all game statuses
  - `routes/web.php` — reorganized game routes into 3 groups: page routes (with middleware), polling/API endpoints, and POST action routes
  - `app/Http/Controllers/TopicController.php` — removed ad-hoc `status !== 'submitting'` redirect from `show()`
  - `app/Http/Controllers/TurnController.php` — removed ad-hoc `complete`, `round_complete`, and `!== 'playing'` redirects from `show()`
- **Learnings for future iterations:**
  - The middleware only applies to game page GET routes (Inertia renders), not polling endpoints or POST actions — this is enforced via route grouping, not request type detection
  - `$request->is()` matches URL paths against patterns — useful for comparing current URL to expected URL
  - The `grading` status (while AI is grading) maps to `/play` since the player should stay on the play screen
  - `grading_complete` requires querying the latest completed turn to build the results URL
  - Existing tests that checked controller-level redirects (e.g., `TopicSubmissionTest`, `GameCompleteTest`) continue to pass because the middleware performs the same redirects
  - DevController has a bug: `joinGame()` uses unscoped `'player_id'` session key instead of `"player_id.{$code}"` — may need fixing in future stories
---

## 2026-02-26 - US-004
- What was implemented: Player reconnection via localStorage token — guest players can rejoin games after losing their session by revisiting the join URL
- Files changed:
  - `app/Http/Controllers/JoinController.php` — added `reconnect()` endpoint + flash reconnect data in `store()`
  - `app/Http/Middleware/RedirectToGameState.php` — made `correctUrlForStatus()` public static for reuse
  - `app/Http/Middleware/HandleInertiaRequests.php` — share flash `reconnect_data` via Inertia shared props
  - `resources/js/pages/games/Join.vue` — check localStorage on mount, attempt reconnect via API, show "Reconnecting…" state
  - `resources/js/pages/games/Lobby.vue` — store reconnect data from flash into localStorage on mount
  - `app/Http/Controllers/GameController.php` — touch `updated_at` on guest player in `players()` and `submissionStatus()` polling endpoints
  - `app/Http/Controllers/TurnController.php` — touch `updated_at` on player in `playState()`, `chooseTopic()`, `startRecording()`, `storeAudio()`
  - `routes/web.php` — added `POST /join/{code}/reconnect` route
  - `tests/Feature/ReconnectTest.php` (new) — 8 tests covering full reconnect flow
- **Learnings for future iterations:**
  - To pass data across an Inertia redirect (POST → redirect → new page), use Laravel session flash (`->with()`) combined with Inertia shared props in `HandleInertiaRequests::share()`
  - The reconnect endpoint validates `reconnect_token` against the `players` table, filtered to `user_id IS NULL` (guests only) and `updated_at` within 10 minutes
  - The `X-XSRF-TOKEN` header must be decoded from the cookie via `decodeURIComponent` — Laravel encodes the CSRF token in the cookie
  - `$player->touch()` is an efficient way to update `updated_at` without changing other fields
  - The `flushSession()` method in tests clears session data — useful for simulating session loss in reconnect tests
---

## 2026-02-26 - US-005
- What was implemented: Host reconnection from dashboard — active games show a "Rejoin" button that navigates to the correct game screen
- Files changed:
  - `app/Http/Controllers/DashboardController.php` — added `rejoin_url` field using `RedirectToGameState::correctUrlForStatus()`, only for non-complete games
  - `resources/js/pages/Dashboard.vue` — added `rejoin_url` to `GameSummary` interface, added Actions column with blue "Rejoin" button that renders conditionally when `rejoin_url` is non-null; also fixed pre-existing unused `props` variable lint error
- **Learnings for future iterations:**
  - `RedirectToGameState::correctUrlForStatus()` is the single source of truth for game status → URL mapping — reuse it whenever you need to compute where a player should be
  - The `grading_complete` status requires a DB query (to find the latest completed turn), so computing `rejoin_url` server-side is better than client-side for this status
  - Dashboard games table already had all the data needed — just needed to add the computed URL from the controller
  - The `DashboardTest` suite covers the dashboard page rendering — 6 tests all pass with the changes
---

## 2026-02-26 - US-006
- What was implemented: Added a polling status indicator — a small green dot in the bottom-right corner of all game screens that pulses on each successful poll and turns orange on failure
- Files changed:
  - `resources/js/components/PollIndicator.vue` (new) — reusable component accepting `lastPollAt` and `error` props; uses CSS transition for pulse animation
  - `resources/js/pages/games/Lobby.vue` — added `lastPollAt`/`pollError` refs, updated `pollPlayers()` to track success/error, added PollIndicator to template
  - `resources/js/pages/games/Submit.vue` — added `lastPollAt`/`pollError` refs, updated `pollStatus()` to track success/error, added PollIndicator to template
  - `resources/js/pages/games/Play.vue` — added `lastPollAt`/`pollError` refs, updated `pollState()` to track success/error, added PollIndicator to template
- **Learnings for future iterations:**
  - PollIndicator is positioned `fixed` so it works in both host (AppLayout with sidebar) and guest (full-screen) views without layout-specific adjustments
  - The pulse animation uses a `setTimeout` to toggle a `pulsing` ref that adds `scale-150 opacity-80` Tailwind classes, driven by a `watch` on `lastPollAt` — simple and avoids CSS animation retrigger complexity
  - Both non-200 responses and network errors (catch) should update `lastPollAt` alongside `pollError` to ensure the indicator reflects the latest state
  - The component is placed outside the `v-if`/`v-else` layout blocks (host vs guest) so it renders once regardless of which view is active
---

## 2026-02-26 - US-007
- What was implemented: Collapsible QR code and join link panel on host Submit and Play screens, allowing hosts to re-share the join info with players who dropped
- Files changed:
  - `resources/js/components/JoinLinkPanel.vue` (new) — reusable collapsible panel with QR code, game code, and shareable link
  - `resources/js/pages/games/Submit.vue` — import and render JoinLinkPanel in host view
  - `resources/js/pages/games/Play.vue` — import and render JoinLinkPanel in host view
- **Learnings for future iterations:**
  - The project already has a `Collapsible`/`CollapsibleTrigger`/`CollapsibleContent` UI component set (from reka-ui) — use these for any toggle/disclosure patterns
  - The `joinUrl` can be constructed client-side as `window.location.origin + '/join/' + gameCode` — no need to pass it as a prop from the server for secondary screens
  - QR code generation uses the `qrcode` npm package with `QRCode.toDataURL()` — async, must be called in `onMounted`
  - Host views in game pages use `AppLayout` wrapper with a `v-if="player.is_host"` / `v-else` pattern — host-only UI goes inside the AppLayout block
  - The JoinLinkPanel is placed after the main content area but inside the AppLayout block, so it appears at the bottom of the host's view
---

## 2026-02-26 - US-008
- What was implemented: Expanded DevSeeder with 5 new game state scenarios for E2E testing, plus 5 new host user accounts
- New scenarios:
  1. **Submitting (host view)** — `host-submitting@dev.test` — game in `submitting` status, host hasn't submitted yet, 2 guests also not submitted
  2. **Submitting (all submitted)** — `host-ready@dev.test` — game in `submitting` status, all 3 players submitted 3 topics each, host sees "Start Game"
  3. **Playing (choosing)** — `host-choosing@dev.test` — game in `playing` status, first turn is `choosing` with `topic_choices` populated, second turn is `pending`
  4. **Grading complete** — `host-grading-done@dev.test` — game in `grading_complete` status, first turn complete with score/feedback, second turn pending
  5. **Round complete** — `host-round-done@dev.test` — game in `round_complete` status with `max_rounds=2`, both turns in round 1 complete
- Files changed:
  - `database/seeders/DevSeeder.php` — added 5 new users + 5 new game scenarios with full player/topic/turn data
  - `app/Http/Controllers/DevController.php` — updated `index()` to use `LIKE 'host-%@dev.test'` pattern instead of hardcoded email list, so new scenario users auto-appear
  - `resources/views/dev/index.blade.php` — added descriptions for new scenario accounts, added CSS badge styles for `grading_complete` and `round_complete` statuses
- **Learnings for future iterations:**
  - DevController queries were hardcoded to specific emails — using `LIKE 'host-%@dev.test'` pattern is more maintainable and auto-includes new scenario users
  - The `topic_choices` column on turns must be populated as an array of topic IDs for the `choosing` state to work correctly in the UI
  - For `round_complete` scenarios, set `max_rounds=2` so the host has a "Start Next Round" button (with `max_rounds=1`, it would go to `complete`)
  - Each scenario's comment block documents which user to log in as and what they should see — critical for E2E test authors
---

## 2026-02-26 - US-009
- What was implemented: Playwright test infrastructure with seed-based pattern — global setup, shared helpers, and npm script
- Files changed:
  - `tests/e2e/global-setup.ts` (new) — Playwright global setup that runs `php artisan migrate:fresh --seed --force` once before the entire test suite
  - `tests/e2e/helpers.ts` (new) — shared helper functions: `loginAs(page, userId)` and `expectUrl(page, urlPattern)`
  - `playwright.config.ts` — added `globalSetup` pointing to `tests/e2e/global-setup.ts`
  - `package.json` — added `"test:e2e": "playwright test"` script
  - `tests/e2e/game-complete.spec.ts` — removed per-file `beforeAll` seed (now handled by global setup), fixed strict locator for "Rapid Owl"
  - `tests/e2e/results.spec.ts` — removed per-file `beforeAll` seed (now handled by global setup)
  - `app/Http/Middleware/RedirectToGameState.php` — allow viewing individual turn results for `complete` games (not just `grading_complete`)
- **Learnings for future iterations:**
  - Playwright global setup runs once before the entire suite — much faster than per-file `beforeAll` seeds (4s total vs ~7s per file)
  - The `loginAs()` helper navigates to `/dev/login-as/{userId}` and waits for redirect to `/dashboard` — userId is the numeric database ID
  - `expectUrl()` accepts both string and RegExp patterns, wrapping Playwright's `toHaveURL()`
  - The `RedirectToGameState` middleware blocks results page access for `complete` games — had to add `complete` to the allowed statuses for `/results/*` URLs
  - Pre-existing test bugs: "Rapid Owl" text matched 3 elements (strict mode violation) — use `getByRole('heading', ...)` for unique matches
  - Playwright browsers must be installed separately via `npx playwright install chromium` — the `@playwright/test` npm package doesn't include them
  - The `APP_URL` env var (default: `http://how-does-that-work.test`) is used as `baseURL` in Playwright config — tests use relative URLs like `/dev/login-as/1`
---
