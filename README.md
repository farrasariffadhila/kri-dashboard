# IT Risk KRI Dashboard

A board-level dashboard that turns IT operations and security metrics into one composite risk score (0–100, higher is worse) per department. It also includes the workflows that keep those numbers honest: remediation of audit findings, and disputes over KRI figures.

![Overview](screenshots/02-overview.png)

> Portfolio project. All data is synthetic, produced by the included Python generator. Not affiliated with any bank.

## Run it

```bash
docker compose up --build
```

Open http://localhost:8080 and pick a demo account. Docker is the only requirement.

The database (SQLite inside the container) is rebuilt and re-seeded on every start, so each run begins with a clean demo.

| Demo account | Role | Things to try |
|---|---|---|
| Hendra Wijaya | Board of directors | Read the overview, open a department, filter findings |
| Rina Kartika (one owner per department) | Unit owner | Submit an action plan or evidence for a finding; dispute a KRI figure |
| Fajar Nugroho | Internal auditor | Approve or return submissions; verifying evidence closes the finding |
| Maya Anggraini | IT Risk | Accept or reject KRI disputes. One is already waiting. |

Use **View as** in the top bar to switch persona without signing out. That way you can walk one finding or dispute through every role.

## Tour

### Sign-in

![Sign-in](screenshots/01-login.png)

- **Demo mode:** choose a persona, no password needed.
- **Real authentication underneath:** session cookies with CSRF protection (Laravel Sanctum), throttled sign-in, and a generic error message.
- **Production setting:** `KRI_DEMO_MODE=false` hides the picker and leaves only the email and password form.

### Overview

The page opens with one sentence that names the riskiest department and why, followed by bank-wide KPIs with the change since last month.

The heatmap is a department × KRI register. Every cell carries a colour, an icon and a text label, so status never depends on colour alone.

![Heatmap](screenshots/03-heatmap.png)

![Six-month trend](screenshots/04-trend.png)

### Drill-down and department page

Every score explains itself: the value of each indicator, where Warning and Critical start, its score, its weight, and how many points it contributes.

![Drill-down dialog](screenshots/05-drilldown.png)

![Department page](screenshots/06-department.png)

### Audit findings and server inventory

Server-side filtering, sorting and pagination. Filters live in the URL, so a filtered view can be shared.

![Audit findings](screenshots/07-audit-findings.png)

![Server inventory](screenshots/08-servers.png)

### Methodology and data lineage

The weights, thresholds and a worked example are rendered from the same configuration the API scores with.

For each KRI, the page also shows:

- the source system and data owner;
- the last import, with its row count and SHA-256 checksum.

![Methodology](screenshots/09-methodology.png)

![Data lineage](screenshots/10-data-lineage.png)

### Remediation of audit findings

The unit owner (1st line) proposes an action plan and later uploads evidence. The internal auditor (3rd line) approves or returns each step. Verified evidence closes the finding, and the department's KRI drops immediately.

Every action lands in an append-only audit trail.

![Work queue](screenshots/11-work-queue-owner.png)

![Auditor reviewing an action plan](screenshots/12-finding-review.png)

### KRI data disputes

KRI figures cannot be edited by hand. If a unit owner believes a figure is wrong (for example, decommissioned servers still counted as unpatched), they file a dispute with a reason and evidence. IT Risk (2nd line) accepts it, possibly with an adjusted figure, or rejects it with a reason.

When a dispute is accepted:

- the corrected figure feeds the department score and the bank totals;
- the source figure stays visible next to it, with a link to the dispute.

![Filing a dispute](screenshots/13-dispute-form.png)

![IT Risk reviewing a dispute](screenshots/14-dispute-review.png)

![Corrected figure on the department page](screenshots/15-dispute-correction.png)

![Disputes list](screenshots/16-disputes.png)

### Mobile, dark mode, two languages

<p>
  <img src="screenshots/17-mobile.png" width="260" alt="Overview on a phone">
  <img src="screenshots/18-mobile-menu.png" width="260" alt="Navigation drawer on a phone">
</p>

![Dark mode](screenshots/19-dark.png)

The app is available in Indonesian (default) and English. Server messages follow the chosen language too.

![English](screenshots/20-english.png)

## How the score works

Each KRI is mapped to a 0–100 score with four anchors, interpolated linearly between them:

| KRI | Weight | Score 0 (appetite) | 50 (tolerance) | 75 (limit) | 100 |
|---|---|---|---|---|---|
| Unpatched servers (% of servers) | 35% | 2% | 10% | 20% | 40% |
| Open high-risk audit findings (count) | 25% | 0 | 5 | 10 | 20 |
| Critical application uptime (%) | 25% | 99.95% | 99.90% | 99.50% | 99.00% |
| Phishing test failures (% of employees) | 15% | 3% | 10% | 20% | 30% |

- **Composite score:** the weighted mean of the four KRI scores. If a KRI has no data, the remaining weights are re-normalised.
- **Bands:** Safe is below 50, Warning is 50 to 75, Critical is above 75.
- **Reporting period:** the month is pinned by `KRI_REPORTING_MONTH`, not taken from today's date, because the report is a month-end close.

## Design decisions

- **Three lines of defence.** Each role can do only its own step. Unit owners act, IT Risk reviews data, internal audit verifies. Every rule is enforced in the backend, not just hidden in the UI.
- **Maker-checker.** Nobody can approve their own submission. Every transition runs in a database transaction with row locks.
- **No manual KRI input.** Figures come from logged, checksummed imports. The only correction path is an accepted dispute, and the original figure stays visible.
- **Append-only audit trail.** The model refuses updates and deletes on log entries.
- **Need to know.** A unit owner cannot open another department's evidence or disputes.
- **Evidence uploads.**
  - Files go to private storage under random names.
  - The file type is checked by content sniffing, not by extension.
  - Each file gets a SHA-256 hash.
  - Downloads always come as attachments with `nosniff`.
- **Accessibility.**
  - Status is never shown by colour alone.
  - axe-core reports 0 violations in light and dark mode.
  - Every page works with the keyboard and fits a 320 px screen without horizontal scrolling.
- **Hardened container.**
  - Strict Content Security Policy (`script-src 'self'`) and HSTS.
  - `X-Frame-Options: DENY`.
  - Debug mode off.

## Tech stack

| Layer | Tools |
|---|---|
| Backend | Laravel 11, PHP 8.4, Laravel Sanctum (SPA cookie auth) |
| Frontend | Vue 3.5, Vite 8, Tailwind CSS 4, Chart.js 4, vue-router, vue-i18n |
| Data | Python 3.13, pandas, Faker |
| Container | nginx + PHP-FPM on Alpine, multi-stage build (about 130 MB) |

## Tests

```bash
cd backend && php artisan test
```

There are 70 unit and feature tests, covering:

- scoring;
- API shape;
- authentication and demo sign-in;
- the remediation and dispute workflows, including role checks;
- data lineage.

The tests run on in-memory SQLite.

## Local development without Docker

You need PHP 8.4, Composer, Node 22 and MySQL or MariaDB. Python 3.13 is only needed to regenerate the data.

```bash
# API on http://127.0.0.1:8000
cd backend
cp .env.example .env            # set DB_* for your database
composer install
php artisan key:generate
php artisan migrate --seed      # imports data-generator/output/*.csv and creates the demo accounts
php artisan serve

# SPA on http://localhost:5173 (proxies /api to the API)
cd frontend
npm install
npm run dev
```

To regenerate the synthetic data, see [data-generator/README.md](data-generator/README.md).

## Docker image

`docker compose up` runs the same image a host would run. nginx serves the Vue build and forwards `/api` and `/sanctum` to PHP-FPM, so the SPA and the API share one origin and the session cookie works without CORS.

These environment variables change its behaviour:

| Variable | Default | Effect |
|---|---|---|
| `KRI_RESET_ON_BOOT` | `true` | Rebuild and re-seed the database on start. Set it to `false` for a persistent database. |
| `KRI_DEMO_MODE` | `true` | Persona picker on the sign-in page |
| `DB_CONNECTION` | `sqlite` | Set it to `mysql` and add the `DB_*` variables to use MySQL or MariaDB |
| `APP_KEY` | generated per start | Set a fixed key if sessions must survive a restart |
| `PORT` | `8080` | Port nginx listens on |

For persistent evidence files, mount a volume on `/app/backend/storage/app/private`.

## Repository layout

```
backend/          Laravel API: scoring, workflows, tests
frontend/         Vue SPA
data-generator/   Python generator for the synthetic CSV data
docker/           nginx, PHP and entrypoint configuration for the image
screenshots/      Images used in this README
```

More detail in [frontend/README.md](frontend/README.md), [backend/README.md](backend/README.md) and [data-generator/README.md](data-generator/README.md).
