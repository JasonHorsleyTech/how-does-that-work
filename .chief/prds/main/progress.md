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
- Polling in all game pages uses raw `setInterval` (no composable) with 3-second intervals
- Game page routes (GET Inertia pages) are wrapped in `RedirectToGameState` middleware; polling/API/POST routes are NOT
- Game status → correct URL mapping is in `RedirectToGameState::correctUrlForStatus()` — use this as the source of truth for redirect rules
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
