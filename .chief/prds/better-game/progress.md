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
  - Pre-existing BillingTest Stripe webhook failures (4 tests) are unrelated to game logic — ignore when validating changes
---
