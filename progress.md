## Codebase Patterns
- Laravel 12 + Vue/Inertia starter kit with Laravel Fortify for auth
- SQLite database in development (DB_CONNECTION=sqlite in .env)
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
