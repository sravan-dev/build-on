# build-on

Buildon Accounts — projects, quotations, invoices, purchases, payroll and GL for
Buildon Trading & Contracting W.L.L.

PHP 8 + MySQL/MariaDB, no build step. Serve the repository root with any PHP host.

## First run

The installer writes `.env`, creates the database when the account is allowed to,
and imports `sql/buildon_qatar.sql` — but only into an **empty** database, so it
can never overwrite a live installation. Re-running it is safe.

### Shared hosting (no Node) — use the PHP installer

1. Upload the repository to the web root.
2. Create the database and user in cPanel (most shared hosts do not allow
   `CREATE DATABASE` from PHP; the installer skips that step and carries on).
3. Arm the web installer with a one-time token:

       php sql/db.php --make-token

   (No shell at all? Create `sql/.setup-token` by hand containing a long random
   string.) Skip this step entirely if you can run `php sql/db.php` from a cPanel
   Terminal or cron job — the CLI needs no token.
4. Open `https://your-site/sql/db.php?token=<the token>` and fill in the database
   details.
5. When it reports success, **delete `sql/db.php` and `sql/db.config.json`** from
   the server.

The web installer refuses to run unless the token matches, refuses once `.env`
exists (a configured site is never reinstallable over HTTP), and disarms itself
after any completed browser run by writing `sql/.installed` and deleting the
token. Without that gate, whoever reached the URL first could point the
application at a database of their own.

### Where it has Node

    node sql/db.js

Same three steps, same safety rules.

### Credentials

Never committed. The installer takes them from, in order:

1. the setup form (browser) or environment variables,
2. `sql/db.config.json` — copy `sql/db.config.example.json` and fill it in; it is
   gitignored,
3. the non-secret defaults in the script (host, port, database name).

`.env` itself is gitignored; `.env.example` is the template.

## Layout

| Path | What it holds |
| --- | --- |
| `index.php` | Front controller: routing, session, and the POST handlers that need to redirect before output |
| `pages/` | One file per screen, plus the printable `invoice_view.php` / `quotation_view.php` documents |
| `includes/` | `db.php` (PDO bootstrap), `auth.php`, `functions.php` (shared helpers, GL vouchers) |
| `ajax/` | JSON endpoints for the quick-create modals |
| `database/` | Numbered migrations to apply to an existing installation |
| `sql/` | First-run installer and the seed dump |
| `vendor/` | Composer dependencies, committed so hosts without Composer can run the app |

## Upgrading an existing installation

Do not re-import the dump. Apply the migrations in `database/` that you have not
run yet, newest work last:

    mysql -u USER -p DBNAME < database/add_invoice_project_id.sql
    mysql -u USER -p DBNAME < database/add_purchase_returns.sql
    mysql -u USER -p DBNAME < database/add_quotation_discount.sql

## Security notes

- `ENABLE_QUICK_LOGIN` must be `false` in production. When it is on, a bare POST
  of `quick_login=1` grants a superadmin session with no credentials. The
  installer writes `false` into every `.env` it generates.
- The repository root still contains one-shot `check_*`, `debug_*`, `fix_*`,
  `diagnose*` and `migrate_*` scripts that query the database without a session
  check. Delete them from any public server.
