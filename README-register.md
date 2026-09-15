# Registration backend — setup

This delivery has six files total, but they don't all go the same place:

**Upload these four to your PHP host** (they reference each other by
relative path, so keep them in the same folder):
- `register.html` — the form (team name, leader name, team roster)
- `register.php` — validates input and writes to MySQL
- `admin.php` — password-gated page to see who's registered
- `config.php` — your DB connection details + admin password

**Run this once, against your database** (not uploaded to the web host):
- `schema.sql` — creates the database + tables

**Reference only:**
- `README-register.md` — this file

## What changed from the first version

- No college field, no passwords — this isn't a login system, it's just
  a form for organizers to see who's registered.
- No contact email/phone either — the only identifying info is team name
  + leader/member names.
- **Team leader** is just a name (no class field) — since the leader must
  be a final-year student, that's implied and not asked for separately.
- **Team members** (1–5 of them) each get a name and class, added with
  "+ Add member".
- `admin.php` lists every team — name, leader, member roster — gated by a
  single admin password (not per-user accounts).

## Setup

1. **Create the database.**
   ```
   mysql -u root -p < schema.sql
   ```
   This creates `fracture_point` with two tables: `registrations` (team
   name + leader name) and `team_members` (one row per additional member,
   linked back to their team, each with a class).

   On shared hosting (cPanel, Hostinger, etc.) you often can't run
   `CREATE DATABASE` yourself — the panel creates the DB for you with a
   fixed name. In that case, open `schema.sql`, delete the first
   `CREATE DATABASE` / `USE` lines, and run just the two `CREATE TABLE`
   statements against the database the panel already gave you. Then match
   that same database name in `config.php`.

2. **Fill in `config.php`** — your DB host/name/user/password, and set
   `ADMIN_PASSWORD` to something only organizers know.

3. **Upload the four PHP/HTML files** (`register.html`, `register.php`,
   `admin.php`, `config.php`) to a PHP-enabled host (PHP 7.4+ with the
   `pdo_mysql` extension — standard on shared hosts, cPanel, XAMPP/MAMP).

4. **Link `register.html`** from your main site's nav. **Don't** link
   `admin.php` publicly — just bookmark the URL yourselves.

## What's already handled

- All DB queries use prepared statements (PDO) — safe from SQL injection.
- Server-side validation runs independently of the front-end checks. A
  member row with a name but no class (or vice versa) is rejected with a
  clear message rather than silently dropped.
- Duplicate team names are rejected.
- Registration (team row + all member rows) happens in one transaction, so
  a team is never left half-saved.
- Admin page output is HTML-escaped to prevent stored XSS from roster names.
- Works even if the `mbstring` PHP extension isn't enabled on your host.
- If the database connection itself fails (wrong credentials, DB not
  reachable), `register.php` returns a clean error instead of crashing.

## Worth adding before a real launch

- **HTTPS** on whatever domain hosts this.
- **Rate limiting** on `register.php` to slow down spam/bulk submissions.
- A **CAPTCHA** (hCaptcha/Cloudflare Turnstile) if you expect bot signups.
- A CSV export button on `admin.php`, if you'd like — ask and I'll add it.
