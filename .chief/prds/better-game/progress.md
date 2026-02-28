## Codebase Patterns
- Join page is at `resources/js/pages/games/Join.vue`, uses Inertia `useForm` for submission
- Mic test logic lives in `Play.vue` using `navigator.mediaDevices.getUserMedia` + Web Audio API `AnalyserNode`
- Audio utilities: `resources/js/utils/audioLevel.ts` (RMS level + speech detection), `resources/js/utils/countdownTimer.ts`
- Player reconnection uses localStorage key `hdtw_player_{game_code}` with `reconnect_token`
- Pre-existing test failures: 4 BillingTest Stripe webhook tests fail (unrelated to game logic)
- Pre-existing TypeScript errors: `FormDataErrors` type doesn't include server-side error keys like `game`, `turn`, `checkout`, `code`
- Guest player pages use a simple full-screen layout (no AppLayout/sidebar), host pages use `AppLayout`
- Audio upload: player uploads to `POST /api/games/{code}/turns/{turnId}/audio`, host uploads to `POST /api/games/{code}/turns/{turnId}/host-upload-audio`
- `storeAudio` validates player owns the turn; `hostUploadAudio` validates user is the host — separate authorization paths
- Host upload accepts multiple audio formats (mp3, wav, webm, m4a, ogg) via Laravel `mimes` validation
- Multiple `ref="..."` in mutually exclusive `v-if`/`v-else-if` blocks works fine in Vue 3 — only one instance rendered at a time
- `AudioVisualizer.vue` component: pass a `MediaStream`, renders frequency bars. Use `getByteFrequencyData` for bars, `fftSize: 64`, `smoothingTimeConstant: 0.7`
- When passing a variable to a child component as a prop, it must be a `ref` — convert `let` variables to `ref()` if needed
- Grading jokes live in `resources/js/utils/gradingJokes.ts` — easy to expand by adding strings to the array
- Fade transitions: use CSS `transition-opacity duration-300` with a reactive `opacity-0`/`opacity-100` class toggle + `setTimeout` for the content swap
- Score display: use `formatScore()` from `resources/js/utils/formatScore.ts` for consistent decimal display (e.g., "72.5")
- Grade derivation: `GradeTurn::gradeFromScore()` derives letter grades server-side (A: 90+, B: 80-89.9, C: 70-79.9, D: 60-69.9, F: <60)
- Score fields are `decimal` in DB, cast to `'float'` in models — use `round(..., 1)` when storing
- `.chief/` directory is in `.gitignore` — use `git add -f` to commit PRD changes
- Many E2E tests were pre-written in bulk commit `56b65dc` — check `game-flow.spec.ts` before writing new tests

---

## 2026-02-27 - US-001
- Implemented microphone permission request on the Join page
- When a player clicks "Join Game", `getUserMedia` is called to trigger the browser's mic permission prompt
- If granted: stream is immediately released, form submits normally
- If denied: warning message shown ("Microphone access was denied. You can still play, but the host may need to upload audio on your behalf."), button changes to "Join Anyway"
- Player can still join even if mic is denied (non-blocking)
- No changes needed to Play.vue — browser remembers mic permission for the origin, so `initMicTest()` won't re-prompt
- Files changed: `resources/js/pages/games/Join.vue`
- **Learnings for future iterations:**
  - Browser mic permissions persist per origin — once granted on Join page, Play.vue's `getUserMedia` call succeeds silently
  - The `micDenied` ref pattern (show warning, change button text, allow proceeding on second click) is a clean UX for non-blocking permission flows
  - Amber color scheme (`bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200`) used for warnings vs `destructive` for errors
---

## 2026-02-27 - US-002
- Implemented host audio upload fallback feature
- Backend: Added `hostUploadAudio` method in `TurnController.php` — validates host is authorized, accepts audio files (mp3/wav/webm/m4a/ogg), stores audio, creates PipelineLog, dispatches TranscribeAudio job
- If turn is in 'choosing' state with no topic picked, auto-selects the first topic choice so the pipeline can proceed
- Route: `POST /api/games/{code}/turns/{turnId}/host-upload-audio` in `routes/web.php`
- Frontend: Added upload button to host view in `Play.vue` — appears in all three host states (choosing, mic check, recording) with file picker
- Upload button styled as subtle secondary action (border button, muted text) to not distract from the main game flow
- Shows uploading state and error messages inline
- Files changed: `app/Http/Controllers/TurnController.php`, `routes/web.php`, `resources/js/pages/games/Play.vue`
- **Learnings for future iterations:**
  - The `resolvePlayer` method returns `[$player, $isHost]` — use `$isHost` for host-only authorization checks
  - Laravel `mimes` validation: use `mimes:mp3,wav,webm,m4a,ogg,mpga` (mpga is the MIME subtype for mp3 files)
  - Host upload needs to handle the case where turn is still in 'choosing' — auto-selecting a topic avoids breaking the transcription/grading pipeline
  - Hidden file input + ref click pattern works well for custom upload buttons in Vue
  - The `state_updated_at` touch on the game is critical — it invalidates the polling cache so all clients pick up the state change
---

## 2026-02-27 - US-003
- Implemented audio visualizer during recording phase
- Created `AudioVisualizer.vue` component that uses Web Audio API `AnalyserNode` with `getByteFrequencyData` for frequency-based bars
- Component accepts a `MediaStream` prop, creates its own AudioContext + AnalyserNode, animates at ~30fps via throttled `requestAnimationFrame`
- 7 vertical bars with smooth transitions, styled with Tailwind (`bg-primary`, rounded, gap-1.5)
- Integrated into the active player's recording view in `Play.vue`, between the topic text and countdown timer
- Only renders on the recording player's phone (guarded by `isActivePlayer && recordingPhase === 'recording'`), not visible to host or other players
- Changed `recordingStream` from `let` to `ref<MediaStream | null>` so it's reactive and can be passed to the visualizer component
- Files changed: `resources/js/components/AudioVisualizer.vue` (new), `resources/js/pages/games/Play.vue`
- **Learnings for future iterations:**
  - `getByteFrequencyData` gives better visual results for bars than `getByteTimeDomainData` (which is better for waveforms)
  - `fftSize: 64` keeps the bin count low (32 bins) which is ideal for a small number of visualizer bars
  - `smoothingTimeConstant: 0.7` prevents bars from being too jittery while still being responsive
  - When a non-ref variable (like `recordingStream`) needs to be passed to a child component as a prop, convert it to a `ref` so Vue's reactivity system can track it
  - CSS `transition-[height] duration-75` on bars provides smooth animation without needing JS-driven transitions
---

## 2026-02-27 - US-004
- Implemented loading jokes during grading wait
- Created `resources/js/utils/gradingJokes.ts` with 7 funny one-liners in a static array (easily expandable)
- Jokes rotate every 4.5 seconds with a 400ms fade-out/fade-in transition using CSS `transition-opacity duration-300`
- Replaced the static "Your explanation is being graded…" text in the `recordingPhase === 'done'` section of Play.vue
- Joke rotation starts via a `watch` on `recordingPhase` when it becomes `'done'`, cleaned up on unmount
- Starts at a random index so players don't always see the same first joke
- Files changed: `resources/js/utils/gradingJokes.ts` (new), `resources/js/pages/games/Play.vue`
- **Learnings for future iterations:**
  - CSS opacity transitions + `setTimeout` for content swap is a clean approach for cycling text — no need for Vue `<Transition>` component
  - Starting at a random index (`Math.floor(Math.random() * length)`) adds variety without complexity
  - The `recordingPhase === 'done'` state in Play.vue is the active player's grading wait screen — polling detects `grading_complete` and navigates away
---

## 2026-02-27 - US-005
- Rewrote the GPT grading prompt for dry roast tone, varied scoring, and decimal precision
- Created migration `2026_02_27_000002_change_scores_to_decimal.php` to change `turns.score` to `decimal(5,1)` and `players.score` to `decimal(6,1)`
- Updated Turn model cast from `'integer'` to `'float'` and Player model cast from `'integer'` to `'float'`
- Prompt now instructs GPT to use full 0-100 range with decimals, quote player's words, and write dry roast feedback
- Grade is now derived server-side via `GradeTurn::gradeFromScore()` (A: 90+, B: 80-89.9, C: 70-79.9, D: 60-69.9, F: <60) instead of trusting GPT
- No longer require `grade` key from GPT response — only `score`, `feedback`, and `actual_explanation`
- Created `resources/js/utils/formatScore.ts` utility for consistent decimal display (`toFixed(1)`)
- Updated Results.vue, Complete.vue, and RoundComplete.vue to use `formatScore()` for all score displays
- Updated GradeTurnTest.php: all tests use decimal scores, added `gradeFromScore` unit test
- Files changed: `app/Jobs/GradeTurn.php`, `app/Models/Turn.php`, `app/Models/Player.php`, `database/migrations/2026_02_27_000002_change_scores_to_decimal.php` (new), `resources/js/utils/formatScore.ts` (new), `resources/js/pages/games/Results.vue`, `resources/js/pages/games/Complete.vue`, `resources/js/pages/games/RoundComplete.vue`, `tests/Feature/GradeTurnTest.php`, `.chief/prds/better-game/prd.json`
- **Learnings for future iterations:**
  - Deriving grade server-side from score is more reliable than trusting GPT to be consistent with grade/score mapping
  - `round(max(0, min(100, (float) $value)), 1)` is the correct way to clamp + round a decimal score
  - Laravel `decimal(5,1)` supports up to 9999.9 — more than enough for 0-100.0 range. Use `decimal(6,1)` for cumulative player scores
  - When changing column types, use `->change()` in migrations with doctrine/dbal
  - `formatScore` utility with `toFixed(1)` ensures consistent "72.5" display rather than "72" or "72.50"
- E2E tests: headless Chromium auto-grants `getUserMedia` — mic permission denial flow does NOT trigger in Playwright
- E2E tests: use `page.getByRole()` and `page.getByPlaceholder()` for element selection — avoid fragile CSS selectors
- E2E tests: `loginAs(page, userId)` helper navigates to `/dev/login-as/{userId}` and waits for `/dashboard` redirect
- E2E tests: global setup runs `migrate:fresh --seed` once — all test data comes from seeders, no per-test setup needed
- E2E tests: `expectUrl()` helper uses regex matching to be protocol-agnostic (Herd serves HTTPS but Playwright baseURL is HTTP)
- E2E tests: scope element assertions to `page.getByRole('main')` to avoid matching sidebar elements (e.g., user name appears in both sidebar and main content)
  - Pre-existing BillingTest Stripe webhook failures (4 tests) are unrelated to game logic — ignore when validating changes
---

## 2026-02-27 - US-006
- Enhanced `tests/e2e/smoke.spec.ts` to be a comprehensive homepage smoke test
- Verifies page title ("How Does That Work?"), hero heading, nav links (Log in, Register), join game input/button, Host a Game CTA, and How to Play section
- Test runs in ~414ms (well under the 5-second requirement)
- Files changed: `tests/e2e/smoke.spec.ts`
- **Learnings for future iterations:**
  - The existing smoke test had `/Laravel/` as the title regex — the actual page title is "How Does That Work?"
  - Use `page.getByRole('link', { name: '...' })` for nav links instead of fragile CSS selectors
  - `page.getByPlaceholder('Game code')` reliably targets the join code input
  - Playwright global setup seeds the database once before all tests — no per-test seeding needed
  - The homepage renders for unauthenticated users with Log in/Register links; authenticated users see Dashboard instead
---

## 2026-02-27 - US-007
- Added "host creates a new game from dashboard" E2E test in `tests/e2e/game-flow.spec.ts`
- Test logs in as HOST_STANDARD (user 3), clicks "Host a Game" from dashboard, clicks "Create Game", verifies lobby redirect with game code and host in player list
- Fixed `expectUrl()` helper in `tests/e2e/helpers.ts` — was doing exact string match which broke on http/https mismatch (Herd serves HTTPS but Playwright baseURL is HTTP). Now converts string paths to regex for protocol-agnostic matching
- This fix also resolved 6 pre-existing test failures in game-flow.spec.ts (all had the same http/https mismatch)
- Files changed: `tests/e2e/game-flow.spec.ts`, `tests/e2e/helpers.ts`
- **Learnings for future iterations:**
  - The `expectUrl` helper had a latent bug: Herd serves over HTTPS but Playwright's `baseURL` is `http://`. Using regex for URL assertions avoids this protocol mismatch
  - When host pages use `AppLayout`, the user's name appears in both the sidebar and main content — use `page.getByRole('main')` to scope assertions to the player list
  - `text=/^[A-Z0-9]{5,6}$/` regex locator works well for matching dynamically generated game codes
  - The "Create Game" form defaults to 1 round — no need to explicitly select a radio button for the basic flow
---

## 2026-02-27 - US-008
- Added "guest joins game via join page" E2E test in `tests/e2e/game-flow.spec.ts`
- Test navigates to `/join/LOBBY1`, fills in the display name "Eager Dolphin", clicks "Join Game", verifies redirect to `/games/LOBBY1/lobby`, and confirms the player name appears in the player list
- Also verifies the "Waiting for host to start" message is visible (guest view)
- Files changed: `tests/e2e/game-flow.spec.ts`
- **Learnings for future iterations:**
  - Headless Chromium auto-grants `getUserMedia` permissions — the mic permission flow (deny → "Join Anyway") does NOT trigger in Playwright tests. The form submits directly after a single click
  - Guest lobby pages use `AuthBase` layout (no sidebar), so no need to scope assertions with `page.getByRole('main')` — unlike host pages
  - `page.getByLabel('Your display name')` reliably targets the name input on the Join page
  - The seeded LOBBY1 game already has 3 players (Host Standard, Quick Parrot, Sly Gecko) — the test adds a 4th
---

## 2026-02-27 - US-009
- Verified existing "host starts submission phase from lobby" E2E test in `tests/e2e/game-flow.spec.ts` (line 62)
- Test was already written in a previous bulk commit (`56b65dc`) — just needed validation and PRD marking
- Test logs in as HOST_STANDARD (user 3), navigates to `/games/LOBBY1/lobby`, clicks "Start Submission Phase", verifies redirect to `/games/LOBBY1/submit`
- All 14 E2E tests pass, test runs in ~421ms
- Files changed: `.chief/prds/better-game/prd.json` (marked passes: true)
- **Learnings for future iterations:**
  - Several E2E tests were pre-written in bulk commit `56b65dc` — check if tests already exist before writing new ones
  - The Lobby.vue `startSubmission()` function POSTs to `/games/{code}/start-submission` and Inertia handles the redirect
  - `.chief/` directory is in `.gitignore` — use `git add -f` to commit PRD changes
---

## 2026-02-27 - US-010
- Verified existing "host submits topics on submit page" E2E test in `tests/e2e/game-flow.spec.ts` (line 71)
- Test was already written in a previous bulk commit (`56b65dc`) — just needed validation and PRD marking
- Test logs in as HOST_SUBMITTING (user 6), navigates to `/games/SUBMIT/submit`, fills in 3 topic fields via `#topic-1`, `#topic-2`, `#topic-3`, clicks "Submit Topics", verifies staying on `/games/SUBMIT/submit` and "Topics submitted!" confirmation
- All 14 E2E tests pass, test runs in ~448ms
- Files changed: `.chief/prds/better-game/prd.json` (marked passes: true)
- **Learnings for future iterations:**
  - Topic inputs on the Submit page use IDs `#topic-1`, `#topic-2`, `#topic-3` — use `page.locator('#topic-N')` to target them
  - After topic submission, the page stays on `/games/{code}/submit` with a "Topics submitted!" confirmation message
  - The submitting game (SUBMIT) is seeded with HOST_SUBMITTING (user 6) who hasn't submitted topics yet — ideal for testing the submission flow
---

## 2026-02-27 - US-011
- Verified existing "host starts game when all topics submitted" E2E test in `tests/e2e/game-flow.spec.ts` (line 88)
- Test was already written in a previous bulk commit (`56b65dc`) — just needed validation and PRD marking
- Test logs in as HOST_READY (user 7), navigates to `/games/READY1/submit`, clicks "Start Game", verifies redirect to `/games/READY1/play`
- Test passes in ~420ms
- Files changed: `.chief/prds/better-game/prd.json` (marked passes: true)
- **Learnings for future iterations:**
  - The READY1 game is seeded with status `submitting` and all 3 players having `has_submitted: true` — this makes the "Start Game" button visible
  - The submit page for the host shows a "Start Game" button when all players have submitted their topics
  - Another pre-written test from bulk commit `56b65dc` — always check existing tests first
---
