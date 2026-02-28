## Codebase Patterns
- Join page is at `resources/js/pages/games/Join.vue`, uses Inertia `useForm` for submission
- Mic test logic lives in `Play.vue` using `navigator.mediaDevices.getUserMedia` + Web Audio API `AnalyserNode`
- Audio utilities: `resources/js/utils/audioLevel.ts` (RMS level + speech detection), `resources/js/utils/countdownTimer.ts`
- Player reconnection uses localStorage key `hdtw_player_{game_code}` with `reconnect_token`
- Pre-existing test failures: 4 BillingTest Stripe webhook tests fail (unrelated to game logic)
- Pre-existing TypeScript errors: `FormDataErrors` type doesn't include server-side error keys like `game`, `turn`, `checkout`, `code`
- Guest player pages use a simple full-screen layout (no AppLayout/sidebar), host pages use `AppLayout`

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
