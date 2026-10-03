# Data Generator

Generates synthetic CSV data for the KRI Dashboard. The output is deterministic: the same seed and reference date always produce the same data.

## Running

```bash
cd data-generator
python3 -m venv .venv && .venv/bin/pip install -r requirements.txt   # first time only
.venv/bin/python generate.py --as-of 2026-09-30 --seed 42            # writes to ./output

cd ../backend
# set DEMO_USER_PASSWORD in .env first (shared password for the local demo accounts)
php artisan migrate:fresh --seed                 # or: php artisan db:seed --class=KriCsvSeeder
```

The backend runs on Laravel 11 and needs PHP 8.2 or later.

`KriCsvSeeder` empties the KRI tables (and any remediation workflow data that points at them) and re-imports every CSV, so it is safe to run more than once. Each import is logged in `data_loads` with its row count and SHA-256 checksum. You can point it to another folder with the `KRI_CSV_PATH` environment variable. The import uses a seeder instead of `LOAD DATA LOCAL INFILE` because `local_infile` is often disabled on MySQL servers.

## What is generated

| CSV | Rows | One row is |
|---|---|---|
| `departments` | 5 | A department |
| `servers` | ~4,000 | A server in the current inventory. Hostnames follow `<dept>-<role>-<env>-<seq>`, e.g. `kk-app-prd-017`. Environments are `prd`, `drc` (disaster recovery site), `uat` and `dev`. Servers on an end-of-life OS (RHEL 7.9, CentOS 7.9, Windows Server 2012 R2, Ubuntu 20.04) are always non-compliant. |
| `server_compliance_snapshots` | 30 | A month-end compliance summary per department, for 6 months. The reporting month matches the `servers` table exactly. |
| `audit_issues` | ~540 | An audit finding from the last 18 months, with a realistic IT finding title and an auditor name. The due date follows the remediation SLA: High 30 days, Medium 60, Low 90. An empty `closed_date` means the finding is still open. |
| `incidents` | ~430 | The monthly availability summary of one critical application in a department. Downtime is built from random outages: a Poisson count, each with a lognormal duration. |
| `phishing_tests` | 30 | One phishing simulation campaign per department per month |

The project reporting period is **September 2026** (default `--as-of 2026-09-30`), so the data covers April to September 2026. If `--as-of` falls in the middle of a month, that month is counted month-to-date.

Finding titles are in Indonesian because they represent records of an Indonesian bank.

## The Kartu Kredit anomaly

Kartu Kredit is the credit card department. In the scenario, a delayed card-platform migration breaks the July–September patch cycles, and a PCI DSS assessment then raises many High findings.

| Month | Unpatched servers | Open High audits | Average uptime |
|---|---|---|---|
| 2026-04 | 6.9% | 1 | 99.93% |
| 2026-05 | 6.8% | 3 | 99.94% |
| 2026-06 | 8.5% | 3 | 99.84% |
| 2026-07 | 12.9% | 3 | 99.74% |
| 2026-08 | 21.6% | 14 | 99.55% |
| 2026-09 | 34.9% | 26 | 99.54% |

Uptime drops because card-platform outages become more frequent and last longer over the last three months. The Kartu Kredit phishing failure rate also rises by 2 points in the last two months.

In September, the other departments sit between 5% and 11% unpatched servers, with 1 to 4 open High audits. HRD and Ultra Mikro have the highest phishing failure rates, at about 12.5%, so the risk picture is not the same across departments.

## Where Faker and pandas are used

- **Faker (`id_ID` locale)** generates the auditor names and the dates findings were identified. The names come from a fixed pool, 12 internal IT auditors and 3 external PCI DSS assessors (QSAs), so names repeat the way a real audit team's would.
- **numpy and pandas** handle the statistical distributions:
  - Poisson for the number of outages.
  - Lognormal for outage duration and time to remediate.
  - Binomial for phishing results.

  They also generate per-department IP subnets and hostnames, and assemble the CSVs.
- Descriptive columns (hostname, title, auditor) use a separate random stream (seed + 1), so changing them never shifts the KRI numbers.

Before writing any CSV, `generate.py` checks that:
- IP addresses and hostnames are unique.
- Every finding's status matches its `closed_date`.
- The reporting-month snapshot matches the `servers` table.
- Kartu Kredit is the worst department on both the server and audit KRIs.
