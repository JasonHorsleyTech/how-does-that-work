## Codebase Patterns
- Main play page is `resources/js/pages/games/Play.vue` — contains both host view (AppLayout) and guest view (simple div), split by `v-if="player.is_host"`
- Host view shows AppLayout with sidebar/breadcrumbs; guest view is a minimal full-screen layout
- Active player recording flow: mic test → recording (120s countdown) → upload → grading (joke rotation) → poll for results
- Non-active players poll `/games/{code}/play-state` every 3 seconds to stay in sync
- PipelineLog tracks pipeline stages via timestamps: `whisper_sent_at`, `whisper_response_at`, `gpt_sent_at`, `gpt_response_at` — use these to determine current grading stage
- Play.vue template has v-else-if chains in BOTH host and guest sections — any new turn state needs blocks in both sections
- The `playState()` endpoint query must include all relevant turn statuses (choosing, recording, grading, grading_failed) or turns in those states become invisible to the frontend
- Host-as-player: the `v-if="player.is_host && !isActivePlayer"` pattern in Play.vue switches the host between observational (AppLayout) and active player (fullscreen recording) views
- Backend tests use Pest PHP and SQLite in-memory; run with `composer test`
- Pre-existing TypeScript errors exist in TwoFactorSetupModal.vue, Billing.vue, Join.vue, Submit.vue, Play.vue (FormDataErrors type issues with Inertia) — these are not regressions
- PHP formatting: run `./vendor/bin/pint` before committing PHP changes
- CSRF tokens are extracted from cookies via `getCsrfToken()` utility in Play.vue

## 2026-02-27 - US-001
- Removed host audio upload fallback UI from Play.vue
- Removed 3 identical upload fallback blocks from host view template (choosing, recording+started, recording+mic-check states)
- Removed associated JS: hostUploadInput ref, hostUploadPhase ref, hostUploadError ref, triggerHostUpload(), handleHostFileSelected()
- Kept getCsrfToken() (shared with normal player upload) and backend host-upload-audio endpoint
- Files changed: `resources/js/pages/games/Play.vue` (148 lines deleted)
- **Learnings for future iterations:**
  - The host upload fallback was duplicated in 3 template blocks — each host state had its own copy of the same upload form
  - The backend endpoint `host-upload-audio` is preserved in routes/web.php and TurnController.php for potential future use
  - Host view and guest view are completely separate template branches — changes to one don't affect the other
---

## 2026-02-27 - US-002
- Added grading wait state with spinner and pipeline-aware status text to Play.vue
- Backend: Extended `playState()` to query `grading` and `grading_failed` turn statuses, added `gradingStage` field derived from PipelineLog timestamps
- Frontend: Added `gradingStage` ref, `gradingStatusText` computed property, and grading/grading_failed template blocks in both host and guest views
- Active player sees spinner + "Processing your speech..." immediately after upload, then "Transcribing your speech..." / "Grading your explanation..." once polling picks up pipeline stage
- Non-active players see spinner + "[Player]'s speech is being transcribed/graded..." with "Hang tight" subtext
- Grading failure shows error with destructive styling instead of hanging on spinner
- Joke rotation starts for all players during grading (not just active player)
- Files changed: `app/Http/Controllers/TurnController.php`, `resources/js/pages/games/Play.vue`
- **Learnings for future iterations:**
  - When turn moves from `recording` → `grading`, it previously disappeared from `playState()` query (only checked choosing/recording) — all relevant statuses must be in the whereIn
  - Active player has a brief window where `recordingPhase === 'done'` but `localTurnStatus` is still `'recording'` (before first poll) — need a transitional UI state for this gap
  - PipelineLog `gpt_sent_at` set + `gpt_response_at` null = grading stage; default (no gpt_sent_at) = transcribing stage
  - GradeTurn `failed()` method doesn't update game status — the game stays in `playing` and players would be stuck without explicit grading_failed detection
---

## 2026-02-27 - US-003
- Allowed host to play the game as a regular player by removing `where('is_host', false)` filter in TurnAssignmentService
- Updated Play.vue template: changed `v-if="player.is_host"` to `v-if="player.is_host && !isActivePlayer"` so host sees recording UI when it's their turn
- When host IS the active player: they see the guest/player view (fullscreen recording UI with topic choice, mic test, recording, upload)
- When host is NOT the active player: they see the observational host view (AppLayout with sidebar)
- Updated TurnAssignmentServiceTest: helper now creates topics for host too; updated expected counts for all tests; added 2-player game test
- Files changed: `app/Services/TurnAssignmentService.php`, `resources/js/pages/games/Play.vue`, `tests/Unit/Services/TurnAssignmentServiceTest.php`
- **Learnings for future iterations:**
  - The host/guest template split in Play.vue uses `v-if`/`v-else` at the top level — adding `&& !isActivePlayer` to the host condition elegantly switches the host to the player view when it's their turn
  - `isActivePlayer` prop is always fresh at page load (set by server in TurnController::show) and never changes mid-page since turn transitions cause full page navigations
  - The `onMounted` logic already handles both cases: active players get mic test setup, non-active players get polling — no changes needed
  - Results.vue advance button naturally only shows after host's turn results since the host is on Play.vue during recording and navigates to Results.vue after grading
  - The `createGameWithPlayers` test helper previously didn't create topics for the host — now it does, which is needed since host gets turns and needs other players' topics
---

## 2026-02-27 - US-004
- Added microphone permission explanation text above the name input on the Join page
- Removed the `getUserMedia` check from the join submit handler — mic permission is no longer triggered on this page
- Removed `micDenied` ref and associated warning UI / "Join Anyway" button logic
- The explanation text matches the AC exactly: "This game needs your microphone because you'll be giving an impromptu speech. We'll ask for mic access when it's your turn."
- Files changed: `resources/js/pages/games/Join.vue` (7 insertions, 36 deletions)
- **Learnings for future iterations:**
  - The join page previously triggered `getUserMedia` on form submit as a pre-check — this was removed per AC since mic should only be prompted during actual recording
  - The `micDenied` state + "Join Anyway" flow was the old approach to handling mic denial; the new approach is simply to explain upfront and defer the actual permission prompt
  - The mic explanation text uses `text-muted-foreground` class for subtle styling consistent with the rest of the AuthBase layout
---
