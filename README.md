# World Cup Pool

A just-for-fun (no money) web app for running a **2026 FIFA World Cup knockout-bracket office pool**.
Players predict the winner of every knockout match (Round of 32 → Final) plus the Final score;
managers enter the real results and the app scores everyone and tracks live standings.

> The pool starts at the **Round of 32** (the 32 knockout teams are entered by a manager). The group
> stage is out of scope.

---

## Tech stack

| Layer | Choice |
|-------|--------|
| Framework | PHP 8.3 · **Laravel 13** |
| Frontend | Blade + Tailwind CSS + Alpine.js (Laravel Breeze) |
| Database | **MySQL / MariaDB** |
| Excel/CSV | phpoffice/phpspreadsheet |
| Auth | Laravel Breeze (email/password) |

Local dev environment: **Laragon** (bundles PHP, MySQL, Composer) on Windows.

---

## Requirements

- PHP 8.3+ with extensions: `openssl, pdo_mysql, mbstring, tokenizer, xml, ctype, json, bcmath, fileinfo, curl, zip`
- Composer 2
- Node.js 18+ and npm
- MySQL 8 / MariaDB

---

## Local setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment (.env already present); generate a key if APP_KEY is empty
php artisan key:generate

# 3. Configure the database in .env
#    DB_CONNECTION=mysql
#    DB_HOST=127.0.0.1
#    DB_PORT=3306
#    DB_DATABASE=worldcup_pool
#    DB_USERNAME=root
#    DB_PASSWORD=

# 4. Create the schema
php artisan migrate

# 5. Build front-end assets
npm run build      # or: npm run dev  (watch mode)

# 6. Run it
php artisan serve  # http://127.0.0.1:8000
```

> **Laragon note:** MySQL must be running (Laragon → *Start All*). The bundled PHP/Composer live under
> `C:\laragon\bin\...`; make sure they're on your PATH (restart the terminal/VS Code after install).

After editing any Blade view with **new** Tailwind utility classes, re-run `npm run build`.

---

## How it works

### Roles
- **Global admin** — approves new pools and can reset any user's password. Grant via CLI:
  ```bash
  php artisan user:make-admin you@example.com          # grant
  php artisan user:make-admin you@example.com --revoke # revoke
  ```
- **Manager** — up to 3 per pool (equal permissions); creates/configures the pool, loads teams,
  invites players, enters results.
- **Player** — joins via invite link, makes picks, views standings.

### Pool lifecycle
`setup → open → locked → complete`

1. **Create a pool** — non-admins' pools start **pending admin approval**; admin-created pools auto-approve.
2. **Load 32 teams + the 16 Round-of-32 matchups** — the full bracket (R16 → Final + Third Place) is generated automatically.
3. **Settings** — per-round points, tie-breaker order, and the pick deadline (shown in *America/Mexico_City*; informational only).
4. **Invite players** — paste emails → unique join links + Outlook-ready `mailto:`. Invited users auto-join as **players** when the invite email matches their account.
5. **Open pool for picks** — once approved + teams + deadline set.
6. Players fill the **cascading bracket pick sheet** (+ predicted Final score).
7. **Close picks** (manual manager action) — locks edits and reveals everyone's picks.
8. **Enter results** per round — bracket propagates, scores recompute (broken-bracket rule), with an audit trail.
9. **Standings** rank by points, then tie-breakers.

### Scoring
A player earns a round's points only if their predicted winner equals the **actual** winner of that match.
Tie-breakers (manager-ordered): **exact Final score → closest total goals → most correct picks → earliest submission**.

### Picks via Excel/CSV
Managers can import one player's picks from a file (**manager → Import picks**):
- Download the per-pool **Excel template** — winner dropdowns that **cascade** (pick R32 winners → later rounds auto-fill), plus a Teams reference tab and Final-score cells.
- Upload a completed `.xlsx` or `.csv`. The importer validates team names, bracket consistency, the Final score, and pool membership.

### Password recovery (no email server)
Email is not configured (`MAIL_MAILER=log`). Password resets are **manager-issued** (per pool member) or
**admin-issued** (any user): a temporary password is generated and shown once (with an Outlook send link);
the user is **forced to set a new password** on next sign-in.

---

## Testing

```bash
php artisan test
```

Feature tests run against an in-memory SQLite database (configured in `phpunit.xml`), isolated from your
dev MySQL data. ~90 tests cover pools, bracket building, settings, invites, picks, scoring, standings,
admin/approvals, password reset, and Excel/CSV import.

---

## Deployment (Hostinger / katakum.com) — checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`
- Create a MySQL database in hPanel and update `.env`
- Point the domain's **document root to `/public`**
- `composer install --no-dev --optimize-autoloader` and `npm run build`
- `php artisan migrate --force`
- Serve over **HTTPS**; set `SESSION_SECURE_COOKIE=true`

---

## Project layout (key paths)

```
app/Http/Controllers/   Pool, Bracket, Invite, Pick, PickImport, Result, Standings, Admin*
app/Services/           BracketBuilder, PickResolver, ResultService, StandingsService, PasswordIssuer
app/Policies/           PoolPolicy (view = member, manage = manager)
app/Models/             Pool, PoolMembership, Invite, Team, BracketMatch, Pick, ScoringConfig, ResultAudit, User
database/migrations/    schema
resources/views/pools/  index, show, settings, bracket, picks, results, standings, invites
tests/Feature/          full feature suite
```
