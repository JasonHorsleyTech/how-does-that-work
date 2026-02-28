## Codebase Patterns
- Password validation is centralized: `AppServiceProvider` sets `Password::defaults()`, and `PasswordValidationRules` trait (in `app/Concerns/`) provides `passwordRules()` used by all Fortify actions and form requests
- Frontend password fields have no `minlength` attributes — all validation is server-side via Laravel
- Pre-existing TypeScript errors exist in the codebase (FormDataErrors type issues) — not blocking
- Pre-existing failing tests in BillingTest (Stripe webhook tests) — not related to game logic
- PHP lint uses `composer test:lint` (pint --parallel --test)
- PHP tests use `php artisan test`
- TypeScript check uses `npx vue-tsc --noEmit`

---

## 2026-02-27 - US-001
- What was implemented: Relaxed password requirements from 12-char minimum with complexity rules to 4-char minimum with no complexity requirements, in both production and development
- Files changed:
  - `app/Providers/AppServiceProvider.php` — replaced conditional Password::defaults() with simple `Password::min(4)`
  - `tests/Feature/RegistrationTest.php` — added two boundary tests: 4-char password accepted, 3-char password rejected
- **Learnings for future iterations:**
  - Password validation flows through `Password::defaults()` in AppServiceProvider → `PasswordValidationRules` trait → used by `CreateNewUser`, `ResetUserPassword`, and `PasswordUpdateRequest`
  - No frontend changes needed for password validation — it's all server-side
  - The `PasswordValidationRules` trait is in `app/Concerns/` (not `app/Traits/`)
---
