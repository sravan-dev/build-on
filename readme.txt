Accounts Software Modules 

Programming Language : php

Database : Sqlite (buildon.sqlite)

Frontend : Tailwind 

Color theme : f07d00,f7a600,Black 


### The "Buildon Accounts Dashboard" in Detail

This application is designed to be a one-stop solution for managing a construction business. Here is a more detailed breakdown of each module's purpose and functionality:

—

### 0 Add Login frm .env file set login username as buildon password as buildon

#### 1. Financial Command Center

* **Dashboard**: This is the application's home screen, providing a high-level view of the company's financial health.
    * **Financial Metrics**: Key Performance Indicators (KPIs) like total income, total expenses, net profit/loss, and client dues are prominently displayed. These are calculated in real-time from the data you enter across all modules.
    * **Account Balances**: You can manually update your main bank account, savings account, and credit card balances. The dashboard calculates a net balance to give you a clear picture of available funds.
    * **Data Visualization**: The dashboard includes a bar chart to show expenses by project site and a pie chart to analyze how money is paid out by payment mode (cash, bank transfer, etc.).

---

#### 2. Core Business Management

* **Clients**: This module is a client relationship management (CRM) tool. For each client, you can record contact information, payment preferences, and track their total invoice value and payments received. This helps you quickly identify outstanding balances.
* **Vendors**: Similar to the client module, this section helps you manage your suppliers. You can log their details, track the total amount of business you've done with them, and monitor payments made to them.
* **Projects**: This module lets you track the financial performance of individual projects. You can link each project to a client and log all income and expenses for it, giving you a clear view of the profitability of each job.

---

#### 3. Document and Transaction Flow

* **Quotations**: This is a key starting point for new business. You can create detailed quotations for clients, which include line items for services, quantities, and prices. The most important feature is the ability to change a quotation's status to **'Approved'**, which automatically creates a corresponding invoice and updates the client's account balance.
* **Invoices**: Invoices are automatically generated from approved quotations, saving you time and effort. You can also create them manually. The module tracks each invoice's status (Paid, Partially Paid, Unpaid) and provides a summary of total invoiced, paid, and outstanding amounts.
* **Payment Receipts**: This module links directly to invoices. When a client makes a payment, you can quickly add a receipt, which updates both the client's total paid amount and the specific invoice balance.

---

#### 4. Workforce & Asset Management

* **Payroll**: This module helps you manage your employees. You can input each employee's monthly salary, per-day and per-hour rates, and log advances and deductions. The system calculates the net salary due.
* **Employee Attendance**: This is a new feature that connects to the payroll module. For each employee, you can log their attendance by specifying the number of working days and overtime hours. The system automatically calculates their total earnings for the period based on their defined rates.
* **Outside Labours**: A simple directory to keep track of external contractors, their trade, and their daily rates.
* **Vehicles**: This is a basic asset management tool for logging company vehicles.

---

#### 5. Financial Reporting

* **General Ledger**: This module provides a chronological log of all financial transactions within the company. It pulls data from client payments, vendor payments, and site expenses to provide a complete picture of your financial flow.
* **Cash & Bank Book**: This tool helps you reconcile your accounts by showing a breakdown of all transactions by payment method (cash vs. bank).


====================================
Project Analysis (Codex)
====================================

Overview
- Purpose: Lightweight accounting/ops dashboard for a construction business.
- Stack: PHP (procedural), SQLite (PDO), Tailwind CDN, FontAwesome, Chart.js.
- Auth: Simple session-based login using credentials from `.env`.
- Routing: `index.php?page=...` includes corresponding file from `pages/`.

Project Structure
- `index.php`: Entry point, handles login and routes to pages.
- `includes/`: Shared pieces
  - `db.php`: PDO connection to `buildon.sqlite`.
  - `functions.php`: `.env` loader.
  - `header.php`, `nav.php`, `footer.php`: Layout and navigation.
- `pages/`: Feature modules
  - `dashboard.php`: Top-level KPIs and charts.
  - `clients.php`, `vendors.php`, `projects.php`: CRUD for core entities.
  - `quotations.php` → `invoices.php` → `payments.php`: Commercial flow.
  - `payroll.php`, `attendance.php`, `labours.php`: Workforce modules.
  - `vehicles.php`: Simple asset register.
  - `ledger.php`, `cashbook.php`: Reporting views over `transactions`.
- `database/`: Schema and init
  - `schema.sql`: Complete DDL for all tables.
  - `init.php`: Executes schema against the SQLite DB.
- `tests/test_all.php`: Basic CRUD tests across several tables.
- `assets/css/argon.css`: Additional styles (Tailwind is CDN-loaded).
- `default.php`: Hosting provider placeholder (not used by app).

Setup & Run
- Requirements: PHP with PDO SQLite enabled.
- Configure: Create/update `.env` with `LOGIN_USERNAME` and `LOGIN_PASSWORD`.
- Database:
  - Option 1: Use the committed `buildon.sqlite` (present).
  - Option 2: Recreate DB: run `database/init.php` once to apply `schema.sql`.
- Serve: Point your web server to the project root and open `index.php`.

Database Overview (from `database/schema.sql`)
- Core: `clients`, `vendors`, `projects`.
- Sales: `quotations`, `quotation_items`, `invoices`, `payments`.
- Workforce: `employees`, `attendance`, `outside_labours`.
- Assets/Finance: `vehicles`, `transactions`, `account_balances`, `expenses`, `vendor_payments`.

Notable Behaviors
- Login reads `.env` variables; no registration or password hashing.
- Dashboard aggregates values directly from tables (sums and counts).
- Invoices UI can prefill fields from approved quotations via client-side JS.
- Tests (`tests/test_all.php`) mutate the live SQLite DB (no isolation layer).

Security & Quality Notes
- Credentials in plain text: `.env` with `buildon/buildon` is committed; avoid committing secrets. Use `.env.example` and git-ignore the real `.env`.
- Weak auth: Simple session toggle without password hashing, accounts, or lockouts.
- CSRF: Destructive actions via GET (`?delete=...`) and forms lack CSRF tokens.
- Input validation: Minimal. Some pages use prepared statements; others interpolate input:
  - `pages/attendance.php` performs `SELECT * FROM employees WHERE id = {$_POST['employee_id']}` and similar in one branch; switch to parameterized queries everywhere.
- LFI/path traversal risk in routing: `index.php` includes `pages/$page.php` based only on `file_exists`. Values like `../includes/db` would include files outside `pages`. Sanitize `page` against an allowlist of known pages.
- XSS: Many outputs use `htmlspecialchars`, but ensure consistent escaping of all user-provided fields.
- Data integrity: Columns like `total_invoice`, `total_paid`, `balance` exist but are not consistently maintained by code. Prefer computed values, DB triggers, or centralized update routines.
- Encoding artifacts: Some UI strings show malformed characters (e.g., in `login.php` placeholders). Normalize file encoding to UTF-8.
- CDN dependency: Tailwind/FontAwesome/Chart.js load from CDNs; app won’t render styles offline.

Gaps vs. README Claims
- Automatic invoice creation from approved quotations is not implemented in code. Invoices are listed/created, but there is no server-side automation connecting status changes to invoice creation.

Suggested Improvements (Prioritized)
1) Authentication & session security
   - Hash passwords (e.g., `password_hash`/`password_verify`).
   - Replace `.env` credentials with real user accounts table and roles.
   - Add session hardening (regenerate IDs on login, secure cookie flags).
2) Routing safety
   - Replace free-form `page` param with a strict allowlist map.
3) CSRF protection
   - Add CSRF tokens to all forms; switch destructive actions to POST.
4) Parameterize all queries
   - Remove string interpolation in SQL; use prepared statements everywhere.
5) Validation & sanitization
   - Server-side validate all inputs (types, ranges); consistently escape outputs.
6) Data integrity
   - Either compute financial totals on demand or maintain them centrally (DB triggers or service layer). Avoid duplicating denormalized fields without guarantees.
7) Configuration hygiene
   - Add `.env.example`, `.gitignore` `.env` and `buildon.sqlite`.
8) Testing
   - Isolate tests to an in-memory SQLite or a disposable test DB. Add coverage for invoices/payments flows and security boundaries.
9) UX polish
   - Fix encoding issues; add loading/empty states; consider pagination for large tables.
10) Deployment
   - Provide simple PHP built-in server instructions and/or Dockerfile.

Quick How-To
- Initialize DB: visit/execute `database/init.php` once (if starting fresh).
- Login: credentials from `.env` (e.g., `buildon` / `buildon`).
- Navigate: sidebar in `nav.php` or `index.php?page=MODULE`.
- Run tests: open `tests/test_all.php` in CLI/web, but beware it modifies real DB.

File Pointers
- Routing/Entry: `index.php:1`
- DB Connection: `includes/db.php:1`
- Env Loader: `includes/functions.php:1`
- Schema: `database/schema.sql:1`
- Potential SQLi spot: `pages/attendance.php:14`
- Routing include risk: `index.php:28`

Notes
- `default.php` is a hosting placeholder and can be removed from production.
- Consider moving inline JS into separate assets and adopting a modest MVC structure for maintainability.
