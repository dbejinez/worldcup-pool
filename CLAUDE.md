# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**World Cup Pool** is a just-for-fun (no money) web app for running a 2026 FIFA World Cup knockout-bracket office pool. Players predict knockout match winners (Round of 32 through Final) plus a predicted Final scoreline; managers enter real results; the app scores everyone and displays live standings.

- **Scope**: Knockout stage only (Round of 32 onward); group stage out of scope.
- **Scale**: 1–2 pools, 30–90 players each (~180 users max).
- **Deployment**: Hostinger Premium Web Hosting, subdomain `worldcup.katakum.com`.

## Tech Stack

| Layer | Tech |
|-------|------|
| Framework | PHP 8.3 · Laravel 13 |
| Frontend | Blade templates + Tailwind CSS v3 + Alpine.js (Laravel Breeze) |
| Database | MySQL/MariaDB |
| Auth | Laravel Breeze (email/password) |
| Utilities | phpoffice/phpspreadsheet (Excel/CSV import), flag-icons CDN (country flags) |
| Asset Build | Vite + Tailwind |
| Testing | PHPUnit (in-memory SQLite) |

## Development Commands

### Initial Setup
```bash
# Install dependencies
composer install
npm install

# Generate app key (if APP_KEY is empty)
php artisan key:generate

# Configure database in .env, then:
php artisan migrate

# Build assets
npm run build
```

### Development Workflow
```bash
# Recommended: concurrent dev server, queue, logs, and Vite watcher
composer run dev

# Or individually:
php artisan serve                                    # http://127.0.0.1:8000
php artisan queue:listen --tries=1 --timeout=0     # queue processor
php artisan pail --timeout=0                        # live log tail
npm run dev                                         # Vite watcher

# After editing Blade views with new Tailwind classes:
npm run build
```

### Testing
```bash
# Run full test suite (runs on in-memory SQLite, isolated from dev MySQL)
php artisan test

# Run a single test file
php artisan test tests/Feature/PoolCreationTest.php

# Run a specific test method
php artisan test --filter=test_user_can_create_a_pool_and_becomes_manager

# With output (helpful for debugging)
php artisan test --verbose
```

### Admin Commands
```bash
# Grant/revoke global admin role
php artisan user:make-admin you@example.com
php artisan user:make-admin you@example.com --revoke
```

### Build & Deployment
```bash
npm run build                                    # Production asset build
composer install --no-dev --optimize-autoloader # Production deps
php artisan migrate --force                     # Prod migration
php artisan config:cache route:cache view:cache # Prod caching
```

---

## Architecture Highlights

### Core Concepts

**Pool Lifecycle**: `setup → open → locked → complete`
- **setup**: Manager loads 32 teams and R32 matchups; configures scoring/tie-breakers/deadline.
- **open**: Players make picks (full bracket at once or per-round for incremental pools).
- **locked**: Manager closes picks (full) or locks per-round (incremental); picks become visible.
- **complete**: Tournament finished; all results entered.

**Pool Methods**: 
- **Full**: Players predict the entire bracket up front (cascading pick sheet). Manager sets a global deadline, then manually closes picks.
- **Incremental**: Players pick one round at a time against **actual advancing teams**. Manager manually locks each round after results; next round auto-opens once all matches decided.

**Roles**:
- **Manager** (up to 3 per pool): Create/configure pool, load teams, invite players, enter results, issue temp passwords.
- **Player**: Join via invite link, make picks, view standings.
- **Global Admin**: Approve new pools (non-admin creations are pending), reset any user's password, manage users.

**Scoring**:
- A player earns a round's points **only if their predicted winner equals the actual winner** (broken-bracket rule).
- Configurable points per round (defaults: R32=1, R16=2, QF=4, SF=8, THIRD=4, FINAL=16).
- Tie-breakers (manager-ordered, 4): exact Final score match → closest total goals → most correct picks → earliest submission.

---

### Key Models & Relations

**Pool** (`app/Models/Pool.php`):
- Stateful (status enum: setup/open/locked/complete).
- Stores round-sequence constants (ROUND_SEQUENCE, ROUND_FEEDER) and tie-breaker list (Pool::TIEBREAKERS).
- `isReadyToOpen()`: Needs 32 teams + deadline (full) or just 32 teams (incremental).
- `roundComplete()`, `roundReachable()`, `roundLocked()`, `roundPicksOpen()`: Incremental-specific helpers.

**BracketMatch** (`app/Models/BracketMatch.php`, table `bracket_matches` — `match` is reserved):
- One per knockout match (16 R32 + 8 R16 + 4 QF + 2 SF + 1 THIRD + 1 FINAL = 32 total).
- Seeds via `team_a_id` / `team_b_id` (R32 only; upper rounds filled by winners).
- Bracket wiring: `winner_to_match_id / winner_to_slot` (A or B), `loser_to_match_id / loser_to_slot` (SF losers → THIRD).
- Results: `actual_winner_team_id`, `final_actual_score_a` / `final_actual_score_b` (FINAL only).

**Pick** (`app/Models/Pick.php`):
- One per player per match: `user_id`, `bracket_match_id`, `predicted_winner_team_id`.
- For full pools, all 32 picks submitted at once; incremental, per-round.

**PoolMembership** (`app/Models/PoolMembership.php`):
- Links user to pool (role: manager or player).
- Caches standings: `score`, `correct_picks`, `final_score_a` / `final_score_b`, `picks_submitted_at`.

**Team** (`app/Models/Team.php`):
- Pool-scoped; 32 per pool (R32 participants).
- `country_code` (ISO alpha-2, e.g., 'us', 'gb-eng') for flag display.

**ScoringConfig** (`app/Models/ScoringConfig.php`):
- One per pool; stores per-round points (`pts_r32`, `pts_r16`, ..., `pts_final`).

**Invite** (`app/Models/Invite.php`):
- Manager-generated; token = Str::random(40); auto-joins as player if invited email matches signed-in email.

---

### Service Layer (Business Logic)

**BracketBuilder** (`app/Services/BracketBuilder.php`):
- Called once at pool setup: takes 16 R32 matchups, creates 32 teams, builds full bracket tree (R32 → FINAL, with SF losers → THIRD).
- Wires `winner_to_match_id/slot` and `loser_to_match_id/slot` for cascading results/picks.

**PickResolver** (`app/Services/PickResolver.php`):
- Stateless utility; mirrors bracket wiring logic on the backend.
- **Key method**: `slotTeam($matchId, $slot, $picks)` → resolves which team occupies slot A/B of a match given a player's picks.
- Used for full-pool pick validation (cascading constraint) and for rendering pick sheets.

**ResultService** (`app/Services/ResultService.php`):
- Batch-processes manager's result entries.
- `recordResults()`: Accepts winners + Final scoreline, updates matches, logs result audits, prunes downstream if an earlier result changes, recomputes standings.
- Enforces round order (R32 before R16, etc.).

**StandingsService** (`app/Services/StandingsService.php`):
- `rank()`: Sorts pool members by score, then tie-breaker order (exact score → closest goals → most correct → earliest).
- Cached on PoolMembership; recomputed when results change.

**PasswordIssuer** (`app/Services/PasswordIssuer.php`):
- `issueTemporary($user)`: Generates a secure temp password, sets `must_change_password` flag, returns plain text.
- Used by both manager and global admin resets.

---

### Controllers & Flow

**PoolController**:
- `index`: List user's pools (with role badge).
- `create/store`: New pool (auto-approves if creator is admin, else pending).
- `show`: Pool dashboard (overview, scoring, checklist).
- `settings/updateSettings`: Manager configures per-round points, tie-breaker order, deadline.
- `open/close/reopen`: Manager state transitions + picks reveal on close.
- `lockRound/unlockRound`: Incremental per-round lock (incremental pools only).
- `resetMemberPassword`: Manager issues temp password to a pool member.

**BracketController**:
- `edit/store/destroy`: Manager loads 32 teams + 16 R32 matchups (builds full bracket via BracketBuilder).

**PickController**:
- `edit`: Show pick sheet (full: cascading form; incremental: round-by-round).
- `update` (PUT): Full pool picks submitted; validates consistency via PickResolver.
- `saveRound` (POST): Incremental per-round picks (one round at a time).
- `show`: View another player's picks (gated by manager role or pick visibility).

**InviteController**:
- `index/store/destroy`: Manager invites (Outlook-ready mailto link + unique token).
- `show/accept`: Public landing → auto-join if email matches, else choice page.

**ResultController**:
- `edit`: Form per round (manager-only).
- `update` (PUT): Apply results → ResultService.recordResults() → standings recomputed.

**StandingsController**:
- `index`: Display ranked members with tie-breaker info, pick visibility gated.

**PickImportController**:
- `template`: Download Excel template (cascading dropdowns per round, Teams reference tab).
- `import`: Upload .xlsx or .csv → validates team names, bracket consistency, pool membership.
- Full pools only (not incremental).

**AdminUserController**, **AdminPoolController**:
- Global admin management (list users, reset passwords; approve/reject pending pools).

---

### Authorization Pattern

**Policy**: `app/Policies/PoolPolicy.php`
- `view($user, $pool)`: Any pool member (manager or player).
- `manage($user, $pool)`: Manager only.
- Controllers use `Gate::authorize('view'|'manage', $pool)` (Laravel auto-discovers).

---

### Frontend & UI

**Blade Components** (`resources/views/components/`):
- `flag.blade.php`: Renders flag-icons CDN flag by ISO code.
- Reusable: buttons, forms, modals, nav, dropdowns.

**Layouts**:
- `app.blade.php`: Authenticated users (navbar with brand logo, nav links, session alerts).
- `guest.blade.php`: Public (login, register, invite landing).

**Pool Views** (`resources/views/pools/`):
- `index.blade.php`: My Pools list (role badge, type badge, delete form).
- `show.blade.php`: Pool dashboard (overview, scoring, checklist, action buttons).
- `settings.blade.php`: Manager config (points, tie-breaker order, deadline).
- `bracket/setup.blade.php`: Alpine form for 16 R32 matchups (paste helper, reset).
- `picks/edit.blade.php`: Full pool cascading pick sheet (Alpine client-side cascade mirrors PickResolver).
- `picks/incremental.blade.php`: Incremental per-round forms (open/locked/finished rounds, FINAL score inputs).
- `picks/import.blade.php`: Manager Excel/CSV upload.
- `picks/show.blade.php`: Read-only picks (visibility gated by manager role or reveal status).
- `results/edit.blade.php`: Manager result entry per round.
- `standings/index.blade.php`: Ranked members (tie-breaker columns, pick visibility gated, champion badge/banner).
- `invites/index.blade.php`: Invite form + invite list (token link, Outlook mailto, revoke).

**Theme**: Navy (#0B1F3A) + Emerald (#0F9D58) + Gold (#F4B400); Tailwind v3; Archivo display font (headings).

---

### Database Schema Highlights

**Key migrations** (`database/migrations/`):
- `2026_06_14_000001_create_pools_table.php`: status, deadline_utc, tiebreaker_order (json), created_by.
- `2026_06_14_000003_create_pool_memberships_table.php`: pool_id, user_id, role, score, correct_picks, final_score_a/b, picks_submitted_at.
- `2026_06_14_000006_create_bracket_matches_table.php`: round enum, team_a/b_id, winner_to/loser_to wiring, actual_winner_team_id, final scores.
- `2026_06_24_000001_add_method_and_locked_rounds_to_pools.php`: method (full/incremental), locked_rounds (json array).

**Cascading deletes**: All child tables (memberships, picks, invites, teams, matches) cascade on pool delete.

---

## Testing

- **Framework**: PHPUnit; configured in `phpunit.xml` to use in-memory SQLite (isolated from dev MySQL).
- **Test suite**: ~107 tests in `tests/Feature/` covering:
  - Pool creation/approval/deletion.
  - Bracket setup and team loading.
  - Full and incremental pool workflows.
  - Invite acceptance (including wrong-account handling).
  - Picks submission, cascading validation, visibility.
  - Results recording, scoring, standings (including tie-breaker logic).
  - Admin and manager password resets.
  - Excel/CSV import validation.

**Key test utilities**:
- `Tests\TestCase`: RefreshDatabase trait (auto-migrates SQLite before each test).
- Factories in `database/factories/` for quick model generation.

---

## Important Implementation Notes

1. **Bracket Wiring**: The entire bracket tree (R32 → FINAL) is pre-built at pool creation. Winners/losers from each match are wired to participant slots of the next round via `winner_to_match_id/slot` and `loser_to_match_id/slot`. This tree is immutable; results just fill in the actual winners.

2. **PickResolver Cascade**: Full-pool picks must satisfy bracket constraints—each match's participants are derived from the player's picks in the feeding rounds. `PickResolver.slotTeam()` resolves this recursively; `isCompleteAndConsistent()` validates all 32 picks form a valid bracket.

3. **Incremental Pools**: A separate code path in PickController.editIncremental() and pools/picks/incremental.blade.php. Matches are filled with real advancing teams (from results), not player predictions. Players lock picks per-round; the next round auto-opens when all its matches are decided.

4. **Final Score Tie-Breaker**: Players/managers enter Final scoreline as two inputs (a, b). Validation: champion's predicted/actual score > runner-up's (no ties). Tie-breaker compares predicted total (a+b) to actual total.

5. **Email Disabled**: MAIL_MAILER=log (no SMTP). Password recovery is manager-issued (temp password shown once, user forced to change on sign-in) or admin-issued (global admin can reset any user).

6. **Result Audit Trail**: ResultAudit table logs every result change (who, when, old value, new value). Useful for debugging and appeals.

7. **Incremental Round Locking**: Managers can lock/unlock a round (in ResultController.edit). Unlocking clears downstream results; editing results in locked rounds appends to the audit trail.

8. **Excel Import (Full Only)**: Generates a template with cascading dropdowns for R32→FINAL picks + a Teams reference tab. Validates team-name aliases, bracket consistency, and pool membership on upload.

---

## Configuration & Environment

**Key .env variables**:
- `APP_ENV`: local, testing, or production.
- `DB_CONNECTION`: mysql (prod/dev) or sqlite (testing).
- `MAIL_MAILER`: log (no email).
- `SESSION_DRIVER`: database (manager-issued password stored in sessions).
- `QUEUE_CONNECTION`: database (jobs run synchronously in dev).

**Timezones**: All deadlines stored in UTC (`deadline_utc`); displayed in America/Mexico_City. Timezone conversions handled via Carbon.

---

## Common Modifications

**Adding a new tie-breaker**:
1. Add key/label to `Pool::TIEBREAKERS` constant.
2. Update `UpdatePoolSettingsRequest` validation (tiebreaker_order size).
3. Extend `StandingsService.rank()` compare function to handle the new key.

**Changing scoring defaults**:
- Edit the migration seed defaults in `2026_06_14_000002_create_scoring_configs_table.php`, or update via the settings form.

**Adding a new pool state or round**:
- Extend Pool model constants (ROUND_SEQUENCE, ROUND_FEEDER).
- Update migration enum columns.
- Audit all places that switch on round/status.

**Modifying the bracket skeleton**:
- BracketBuilder.build() orchestrates all bracket construction. Any changes to number of rounds or wiring logic belong there.

---

## Deployment Notes

**Hosting**: Hostinger Premium Web Hosting.
- **Document root**: `/home/u899062816/domains/katakum.com/public_html/worldcup/public` (Laravel /public subfolder).
- **App root**: `/home/u899062816/domains/katakum.com/public_html/worldcup`.
- **.htaccess in app root** (to deny access to .env, code): Required for security.
- **PHP 8.3** with required extensions (openssl, pdo_mysql, mbstring, tokenizer, xml, ctype, json, bcmath, fileinfo, curl, zip).
- **MySQL** local (already created on Hostinger).

**Pre-deployment**:
1. `npm run build` locally (build artifacts committed since no Node.js on server).
2. `composer install --no-dev --optimize-autoloader`.
3. Create prod `.env` (APP_ENV=production, APP_DEBUG=false, DB creds, SESSION_SECURE_COOKIE=true, etc.).
4. `php artisan migrate --force`.
5. `php artisan config:cache route:cache view:cache`.
6. `php artisan user:make-admin {admin-email}`.

---

## Related Documents

- **README.md**: User-facing overview, local setup, pool lifecycle, scoring rules, deployment checklist.
- **HANDOFF.md**: Context for new developers/AI assistants, tech stack, architecture summary, deployment progress.
- **_claude_memory/worldcup-pool-app.md**: Detailed running notes of every decision and feature build (phase-by-phase history).
