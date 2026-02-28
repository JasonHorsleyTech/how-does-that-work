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
