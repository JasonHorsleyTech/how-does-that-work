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
