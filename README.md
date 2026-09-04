# Hydroelectric Generation Monitoring System (HGMS)
A full industrial-style monitoring and control web application for hydroelectric
power stations, built with **PHP (vanilla, PDO)** and **PostgreSQL**, designed
to be developed in **VS Code** and administered through **pgAdmin**.

It covers the entire operational picture a hydro plant control room cares
about: reservoir water level & flood detection, weather analysis, turbine
telemetry and coordinated multi-unit operation, pressure/vibration
monitoring, automatic risk scoring, a rule-based **Alert Bot**, power
generation tracking, and reporting — all behind an admin login.

---

## 1. What's inside (feature map)

| Module | File(s) | What it does |
|---|---|---|
| **Admin Authentication** | `includes/auth.php`, `public/login.php` | Session-based login, bcrypt passwords, role-based access, idle timeout, CSRF protection, activity logging |
| **Dashboard** | `public/dashboard.php` | Fleet-wide KPIs, live 24h power chart, alert feed, "Run Alert Check Now" button |
| **Stations** | `public/stations.php` | Register/edit power stations, capacity, flood thresholds, GPS coordinates |
| **Water Level & Flood Monitoring** | `public/water-level.php` | Live water level trend chart, inflow/outflow, manual/sensor readings, flood event history |
| **Weather Analysis** | `public/weather.php` | Rainfall, temperature, humidity, wind, barometric pressure, forecast notes |
| **Turbine Systems** | `public/turbines.php` | Per-turbine telemetry (RPM, output, pressure, vibration, bearing temp, gate opening %), explains how units share load & reduce pressure collaboratively |
| **Power Generation** | `public/power-generation.php` | Output/energy/grid-frequency/voltage logs, daily/monthly totals |
| **Risk Analysis** | `public/risk-analysis.php` | Composite 0–100 risk scores for flood, mechanical and weather risk, with transparent formulas |
| **Alert Center (Alert Bot)** | `public/alerts.php`, `includes/alert_engine.php`, `cron/alert_bot.php` | Rule-based engine that evaluates live readings, opens/dedupes alerts, opens/closes flood events, acknowledges/resolves workflow |
| **Reports** | `public/reports.php` | Date-ranged generation/alert/flood summaries + CSV export |
| **Admin Users** | `public/users.php` | Create/disable admin accounts, roles: `super_admin`, `admin`, `operator`, `viewer` |
| **Activity Log** | `public/activity-log.php` | Full audit trail of admin actions |
| **Demo Data Simulator** | `cron/simulate_data.php` | Generates realistic sample sensor readings so you can see the whole system working before real hardware is connected |

---

## 2. Technology stack & required tools

| Layer | Technology | Notes |
|---|---|---|
| Language | **PHP 8.1+** | Uses `str_contains`, `match`, typed properties — needs PHP 8+ |
| Database | **PostgreSQL 14+** | Any recent version works |
| DB access | **PDO (`pdo_pgsql`)** | Built into PHP — must be enabled in `php.ini` |
| DB Admin Tool | **pgAdmin 4** | For creating the DB, running `schema.sql`, inspecting data |
| IDE | **VS Code** | With extensions below |
| Frontend | Plain HTML/CSS/JS + **Chart.js** (via CDN) | No build step, no npm required |
| Web server | PHP built-in server, or XAMPP/WAMP/Apache/Nginx | Either works |

### Required PHP extensions
- `pdo_pgsql` (critical — the whole app depends on it)
- `pgsql` (optional, some tooling uses it)
- `session`, `json`, `mbstring` (bundled by default in most PHP installs)

**Enabling pdo_pgsql:**
- **XAMPP (Windows):** open `php.ini`, uncomment `extension=pdo_pgsql` and `extension=pgsql`, restart Apache.
- **macOS (brew):** `brew install php` normally includes it; verify with `php -m | grep pgsql`.
- **Linux (Ubuntu/Debian):** `sudo apt install php-pgsql` then restart your web server.

### Recommended VS Code extensions
| Extension | Publisher | Why |
|---|---|---|
| **PHP Intelephense** | Ben Mewburn | Autocomplete, error-checking for PHP |
| **PHP Debug** | Xdebug team | Step-through debugging with Xdebug |
| **PostgreSQL** | Chris Kolkman / cweijan | Query Postgres directly from VS Code alongside pgAdmin |
| **SQLTools** + **SQLTools PostgreSQL Driver** | Matheus Teixeira | Optional alternative DB browser inside VS Code |
| **DotENV** | mikestead | Syntax highlighting for `.env` |
| **Even Better TOML / EditorConfig** | — | General project hygiene (optional) |
| **Live Server** | Ritwick Dey | Not for PHP execution, but handy for pure static previews |

You do **not** need Composer, Node.js, or any build tool for this project —
it runs as plain PHP files.

---

## 3. Project structure

```
hydro-monitor/
├── README.md
├── .env.example              # copy to .env and fill in your pgAdmin/Postgres credentials
├── .gitignore
├── database/
│   └── schema.sql             # run this in pgAdmin's Query Tool to build everything
├── config/
│   └── database.php            # PDO connection (reads .env)
├── includes/
│   ├── auth.php                 # login, session guard, roles, CSRF
│   ├── functions.php            # helpers: fetchAll/fetchOne, badges, formatting
│   ├── alert_engine.php         # the "Alert Bot" + risk scoring brain
│   ├── header.php               # shared sidebar/topbar layout
│   └── footer.php
├── cron/
│   ├── alert_bot.php            # scheduled entry point for the alert engine
│   ├── simulate_data.php        # optional demo data generator
│   └── generate_password_hash.php  # CLI helper to make bcrypt hashes
├── logs/                        # cron output logs land here
└── public/                       # <-- point your web server at this folder
    ├── index.php
    ├── login.php / logout.php
    ├── dashboard.php
    ├── stations.php
    ├── water-level.php
    ├── weather.php
    ├── turbines.php
    ├── power-generation.php
    ├── risk-analysis.php
    ├── alerts.php
    ├── reports.php
    ├── users.php
    ├── activity-log.php
    ├── assets/
    │   ├── css/style.css        # industrial/SCADA dark theme
    │   └── js/ (reserved for future use — most JS is inline per page)
    └── api/
        ├── get_power_data.php    # feeds the dashboard power chart
        ├── get_water_levels.php  # feeds the water-level trend chart
        └── check_alerts.php      # runs the alert bot on demand (button click)
```

---

## 4. Step-by-step setup

### Step 1 — Create the database in pgAdmin
1. Open **pgAdmin 4** → connect to your local Postgres server.
2. Right-click **Databases → Create → Database…**
3. Name it `hydro_monitor`, owner `postgres` (or your role), Save.
4. Click on the new `hydro_monitor` database to select it, then open a
   **Query Tool** (Tools → Query Tool) **while `hydro_monitor` is selected**
   — this matters, otherwise tables get created in the wrong database.

### Step 2 — Run the schema
1. Open `database/schema.sql` in VS Code, copy its full contents (skip the
   commented `CREATE DATABASE` block at the top — you already did that in
   Step 1).
2. Paste into the pgAdmin Query Tool and click **Execute (▶ / F5)**.
3. You should see all tables, views, and seed data created — including two
   demo stations, five turbines, sensors, and alert rules.

### Step 3 — Configure the app's database connection
1. In VS Code, copy `.env.example` to a new file named `.env` (same folder).
2. Edit the values to match your pgAdmin server:
   ```
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_NAME=hydro_monitor
   DB_USER=postgres
   DB_PASS=your_actual_postgres_password
   ```
3. Save. `.env` is already excluded from git via `.gitignore`.

### Step 4 — Run the app locally
From the project root in a VS Code terminal:
```bash
php -S localhost:8000 -t public
```
Then open **http://localhost:8000** in your browser.

*(Alternatively, place the whole `hydro-monitor` folder in your XAMPP
`htdocs`, and set your Apache virtual host / document root to the `public`
subfolder — never expose `config/`, `includes/`, or `database/` directly to
the web.)*

### Step 5 — Secure the seed admin login
The schema ships a seed admin (`admin` / `Admin@123`) with a placeholder
hash. **Regenerate it locally before real use** since bcrypt hashes can
behave inconsistently across PHP builds:
```bash
php cron/generate_password_hash.php "Admin@123"
```
Copy the printed `UPDATE admins SET password_hash = '...' WHERE username = 'admin';`
line into a new pgAdmin Query Tool tab and run it. Now log in at
`/login.php` with `admin` / `Admin@123` (or whatever password you chose).

### Step 6 — Generate demo data (optional but recommended)
The dashboard and charts are empty until readings exist. Populate realistic
sample data:
```bash
php cron/simulate_data.php
```
Run it a few times (each run adds one more "tick" of readings per station,
including an ~8% chance of a simulated storm surge so you can see flood
alerts fire). Then either click **"Run Alert Check Now"** on the dashboard,
or run:
```bash
php cron/alert_bot.php
```

### Step 7 — Automate the Alert Bot (production)
Schedule `cron/alert_bot.php` to run every 1–5 minutes so alerts, flood
events, and risk scores stay current without manual clicks:

**Linux/macOS (`crontab -e`):**
```
* * * * * /usr/bin/php /full/path/to/hydro-monitor/cron/alert_bot.php >> /full/path/to/hydro-monitor/logs/alert_bot.log 2>&1
```

**Windows (Task Scheduler):**
- Program: `C:\xampp\php\php.exe`
- Arguments: `C:\path\to\hydro-monitor\cron\alert_bot.php`
- Trigger: repeat every 1 minute

---

## 5. How the monitoring logic actually works

### Water level & flood detection
Every reading in `water_level_readings` is compared against each station's
`max_safe_water_level_m` (configured on the Stations page). Crossing it
opens a row in `flood_events` with a severity of `warning` / `critical` /
`emergency` depending on how far over the threshold the level is. When the
level drops back below ~97% of the threshold, the event auto-resolves.

### Weather analysis
`weather_data` stores rainfall, temperature, humidity, wind, and a free-text
forecast summary per station. Rainfall intensity directly feeds the weather
risk score and the "High Rainfall Intensity" alert rule.

### How the turbine system works collaboratively
Multiple turbines per station share one reservoir/penstock. The Turbine
Systems page documents (and the alert engine enforces) three coordination
principles:
1. **Load sharing** — output is intended to be distributed across all
   *online* units rather than maxing out one turbine.
2. **Gate opening = pressure control** — each turbine's wicket-gate
   opening (%) is logged; wider openings pass more flow at lower
   per-unit pressure. The bot watches for units approaching the 13.5 bar
   critical ceiling.
3. **Standby rotation** — turbines marked `standby` are the designated
   next unit to bring online as active units approach vibration/temperature
   limits, spreading wear evenly across the fleet.

### Reducing pressure / preventing mechanical failure
`turbine_status_log` captures `penstock_pressure_bar`, `vibration_mm_s`, and
`bearing_temperature_c` per reading. The alert engine flags a unit
`warning` above 12.5 bar / 3 mm/s, and `critical` above 13.5 bar / 4.5 mm/s
/ 75°C — mirroring real hydro plant SCADA thresholds. Operators should
respond by *increasing gate opening on another online/standby turbine* to
take load off the stressed unit (see Turbine Systems page).

### Risk Analysis scoring (transparent, no black box)
- **Flood risk** = current water level ÷ station flood threshold × 100
- **Mechanical risk** = peak turbine vibration (last 10 min) ÷ 6.0 mm/s × 100
- **Weather risk** = latest rainfall intensity ÷ 60 mm/hr × 100
- Bands: 0–34 Low · 35–64 Moderate · 65–89 High · 90+ Severe

### The Alert Bot
`includes/alert_engine.php` → `runAlertEngine()` is the single source of
truth, called by:
- `cron/alert_bot.php` (scheduled, headless)
- `public/api/check_alerts.php` (dashboard "Run Alert Check Now" button)

It reads each station's latest water/weather/turbine data, evaluates every
row in `alert_rules` against it, opens a new `alerts` row only if an
identical rule isn't already `open` for that station (prevents duplicate
spam), updates flood events, and recomputes risk scores.

**Adding a new alert rule** is pure data — insert a row into `alert_rules`
via pgAdmin, no code changes needed:
```sql
INSERT INTO alert_rules (name, parameter, condition_operator, threshold_value, severity)
VALUES ('Grid Frequency Deviation', 'grid_frequency_hz', '>=', 51.00, 'warning');
```
(Note: to use a new `parameter`, also add it to the `$readings` map inside
`runAlertEngine()` in `includes/alert_engine.php`.)

### Power generation
`power_generation` logs per-turbine (or per-station) output, energy (kWh),
grid frequency and voltage. The dashboard aggregates the last 15 minutes
into "Current Output"; Reports aggregates by day for CSV export.

---

## 6. Connecting real sensors / SCADA (beyond the demo simulator)

`cron/simulate_data.php` exists purely so the UI has data to show before
real hardware is wired in. In a production deployment you would replace it
with one of:
- A small PHP/Python service that polls Modbus/OPC-UA field devices and
  `INSERT`s directly into `water_level_readings` / `weather_data` /
  `turbine_status_log` on the same schedule.
- An MQTT bridge that subscribes to sensor topics and writes to Postgres.
- A vendor SCADA historian that exports to Postgres via `pg_bulkload`/ETL.

As long as new rows land in those three tables, the dashboard, charts,
Alert Bot, and risk scoring all work automatically — no other code changes
required.

---

## 7. Security notes for a DBA / admin

- Passwords are stored with `password_hash()` (bcrypt) — never plaintext.
- All forms use CSRF tokens (`csrfToken()` / `verifyCsrf()`).
- All SQL uses PDO **prepared statements** — no string-concatenated queries
  anywhere in the codebase, so SQL injection surface is minimal.
- Sessions auto-expire after 30 minutes of inactivity (`requireLogin()`).
- Role-based access: `super_admin`/`admin` can manage stations & users;
  `operator` can log readings; `viewer` is read-only (enforce further
  restrictions in `includes/auth.php`'s `requireRole()` calls as needed).
- `.env` (real credentials) is git-ignored; only `.env.example` is committed.
- In pgAdmin, consider creating a dedicated least-privilege Postgres role
  (e.g. `hgms_app`) instead of using the `postgres` superuser in production:
  ```sql
  CREATE ROLE hgms_app LOGIN PASSWORD 'strong-password-here';
  GRANT CONNECT ON DATABASE hydro_monitor TO hgms_app;
  GRANT USAGE ON SCHEMA public TO hgms_app;
  GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO hgms_app;
  GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO hgms_app;
  ```
  Then put `hgms_app` / that password into `.env` instead of `postgres`.
- Back up the database regularly via pgAdmin (right-click database → Backup…)
  or `pg_dump hydro_monitor > backup.sql`.

---

## 8. Typical VS Code + pgAdmin workflow

1. Edit PHP/SQL in **VS Code** (Intelephense gives you inline errors/autocomplete).
2. Run `php -S localhost:8000 -t public` in the VS Code integrated terminal
   to preview changes live.
3. When you need to inspect or tweak data, switch to **pgAdmin**, open the
   `hydro_monitor` database, and use the Query Tool or the table
   Data/Edit view.
4. Schema changes: write a new `.sql` migration snippet (or edit
   `database/schema.sql` for a fresh install), run it in pgAdmin's Query
   Tool, then adjust the matching PHP page.
5. Commit code changes to git as normal — `.env` and `logs/` stay out of
   version control automatically via `.gitignore`.

---

## 9. Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| "Database connection failed: could not find driver" | `pdo_pgsql` extension not enabled in `php.ini` — enable and restart PHP/web server |
| "Database connection failed: password authentication failed" | Wrong `DB_PASS` in `.env` — verify against the password you set in pgAdmin |
| Blank dashboard / no charts | No data yet — run `php cron/simulate_data.php` then `php cron/alert_bot.php` |
| Login always fails with correct password | Seed admin hash is a placeholder — run `cron/generate_password_hash.php` and apply the UPDATE (Step 5) |
| "419 Invalid or expired form submission" | Session expired or two browser tabs submitting stale forms — refresh and retry |
| Alerts never fire | Confirm `alert_rules.is_active = TRUE` and that the relevant `parameter` exists in the `$readings` map in `alert_engine.php` |

---

## 10. License / usage
This codebase was generated as a starting scaffold for an internal
operations tool. Adapt freely for your organization's real hydro plant
requirements — review all thresholds (`max_safe_water_level_m`, pressure/
vibration ceilings, etc.) with your actual engineering specifications before
relying on it for real safety-critical decisions.
