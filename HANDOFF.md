# Project Handoff — World Cup Pool

This document lets **any developer or AI assistant** (Claude, Gemini CLI, OpenAI Codex, GitHub Copilot, Cursor, etc.) pick up this project with full context. Read this + `README.md`, and use `php artisan test` to verify any change.

---

## 1. What this is
A just-for-fun (no money) web app for a **2026 FIFA World Cup knockout-bracket office pool**. Players predict knockout winners; managers enter real results; the app scores everyone and shows live standings. Pool starts at the **Round of 32** (group stage out of scope).

## 2. Tech stack
- **PHP 8.3 · Laravel 13** (Breeze auth: email/password)
- **Blade + Tailwind CSS (v3) + Alpine.js**
- **MySQL/MariaDB** (dev via Laragon)
- **phpoffice/phpspreadsheet** (CSV/Excel pick import)
- Flags via **flag-icons** (jsdelivr CDN); fonts via bunny.net; **email = log driver** (no SMTP)

## 3. Run it locally (new machine)
1. Install **Laragon** (bundles PHP 8.3 + MySQL + Composer) and **Node.js**.
2. In the project folder:
   ```
   composer install
   npm install
   npm run build
   ```
3. Create a local MySQL DB named `worldcup_pool`. Copy `.env` (included) and set:
   `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=worldcup_pool DB_USERNAME=root DB_PASSWORD=`
   Run `php artisan key:generate` if `APP_KEY` is empty.
4. `php artisan migrate`
5. `php artisan serve` → http://127.0.0.1:8000
6. Make yourself a global admin: `php artisan user:make-admin you@example.com`
- Tests: `php artisan test` (runs on in-memory SQLite; ~107 tests). After editing Blade with new Tailwind classes, re-run `npm run build`.

## 4. Architecture (key paths)
- Controllers: `app/Http/Controllers/` — Pool, Bracket, Invite, Pick, PickImport, Result, Standings, AdminUser, AdminPool.
- Services: `app/Services/` — BracketBuilder, PickResolver, ResultService, StandingsService, PasswordIssuer.
- Policy: `app/Policies/PoolPolicy.php` (view = any member, manage = manager).
- Models: Pool, PoolMembership, Invite, Team, BracketMatch (table `bracket_matches`; `match` is reserved), Pick, ScoringConfig, ResultAudit, User.
- Support: `app/Support/CountryFlags.php` (team name → ISO flag code).
- Views: `resources/views/pools/` (index, show, settings, bracket, picks, results, standings, invites), `admin/`.
- Tests: `tests/Feature/`.

## 5. Key product decisions (already implemented)
- **Two pool types (chosen at creation, immutable):**
  - **Full** — predict the whole bracket up front (cascading Alpine pick sheet).
  - **Incremental** — pick one round at a time vs the real advancing teams; manager **manually locks** each round, and the next round **auto-opens** when the previous round's results are all in.
- **Scoring:** per-round points; you earn a round only if your pick == the actual winner (broken-bracket rule). Manager enters winners + the Final scoreline.
- **Tie-breakers (manager-ordered, 4):** exact Final score → closest total goals → most correct picks → earliest submission. Final winner must have strictly more goals (no ties).
- **Deadline is informational**; managers close picks manually (full) or lock per round (incremental). Picks revealed once closed/locked.
- **Admin approval:** non-admin-created pools are **pending** until a global admin approves; admin-created pools auto-approve.
- **Password recovery (no email):** managers issue a temp password to their pool members; global admins can reset any user; user is forced to change it on next sign-in. Grant admin via `php artisan user:make-admin {email} [--revoke]`.
- **Invites:** manager pastes emails → unique join links + Outlook `mailto:`. Opening a link auto-joins as a **player** only if the signed-in email matches the invite (else a choice page).
- **Look & feel:** country flags everywhere; navy/emerald/gold theme; Archivo display font; ball+bracket logo/wordmark "World Cup Pool"; standings medals + gold champion banner + predicted-champion column.
- **CSV/Excel import:** Full-pool only (per-round import for incremental was deliberately NOT built). Template has cascading dropdowns.
- An unused `resources/views/pools/picks/_bracket.blade.php` exists (a visual bracket that was reverted) — safe to delete.

## 6. Deployment status (Hostinger — IN PROGRESS)
Target: **https://worldcup.katakum.com** (Hostinger Premium Web Hosting; main katakum.com is a separate empty site).
Done so far:
- Subdomain created. **Web root:** `/home/u899062816/domains/katakum.com/public_html/worldcup/public`
- **Laravel app root will be:** `/home/u899062816/domains/katakum.com/public_html/worldcup` (its `public/` is the web root).
- **PHP 8.3** set; extensions incl `nd_pdo_mysql`, `nd_mysqli`, mbstring, openssl, zip, curl, gd, fileinfo, bcmath.
- **MySQL DB created** on Hostinger (name/user like `u899062816_worldcup` / `u899062816_wcadmin`; password held by owner). Host `localhost`.
- **SSH access available.**
- Git repo initialized locally; remote `https://github.com/dbejinez/worldcup-pool.git` (⚠ **NOT pushed yet** — do the push from the personal laptop).

Remaining deploy steps:
1. Push the repo to GitHub from the personal laptop (`git push -u origin main`).
2. SSH into Hostinger; clone the repo INTO the app root (remove the auto-created `worldcup` folder first, then `git clone <repo> worldcup`), so `worldcup/public` is the web root.
3. `composer install --no-dev --optimize-autoloader` (Composer is available over SSH; `public/build` is committed since there's no Node on the server).
4. Create the production `.env` in `worldcup/` (File Manager or scp): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://worldcup.katakum.com`, `APP_KEY=` (then `php artisan key:generate`), DB creds (localhost), `SESSION_SECURE_COOKIE=true`, `MAIL_MAILER=log`.
5. `php artisan migrate --force` then `php artisan config:cache route:cache view:cache`.
6. `php artisan user:make-admin dbejinez@arcos-inc.com` (or your email).
7. **Security:** add `worldcup/.htaccess` with `Require all denied` so the app root (with `.env`, code) can't be reached via the main katakum.com site (the subdomain still works — its web root is the `public/` subfolder).
8. Enable HTTPS (Hostinger free SSL) and smoke-test all flows.

## 7. Continuing with another AI assistant
- Point it at this file + `README.md`. The `_claude_memory/` folder (in the transfer zip) has detailed running notes of every decision.
- Verify any change with `php artisan test`.
- Terminal agents closest to the prior workflow: **Gemini CLI** or **OpenAI Codex CLI** (edit files + run composer/artisan/git). Also good: **Aider**, **Cline** (VS Code), **GitHub Copilot**, **Cursor**.
