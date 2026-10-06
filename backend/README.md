# Backend

This is the Laravel 11 API (PHP 8.4). Setup and the score methodology are in the [root README](../README.md).

## Where things live

| Path | Purpose |
|---|---|
| `config/kri.php` | Reporting month, KRI weights and thresholds, rating bands, source systems per KRI |
| `app/Services/RiskScorer.php` | Pure scoring: one KRI score, the weighted composite, the rating |
| `app/Services/DepartmentRiskService.php` | Aggregates KRI figures per department and month, applies accepted disputes, builds bank totals |
| `app/Services/RemediationWorkflow.php` | Audit finding remediation: plan, evidence, auditor review |
| `app/Services/KriDisputeWorkflow.php` | KRI disputes: unit owner files, IT Risk accepts or rejects |
| `app/Services/AttachmentStore.php` | Evidence files on the private disk (random names, SHA-256, sniffed type) |
| `app/Models/ActivityLog.php` | Append-only audit trail |
| `app/Http/Middleware/SetLocaleFromHeader.php` | API messages in Indonesian or English, from `Accept-Language` |
| `database/seeders/` | CSV import (logged with checksums), demo accounts, one demo dispute |
| `lang/{id,en}/` | Server messages in both languages |

## API

Every route except sign-in requires a session (Sanctum SPA auth with CSRF).

| Method and path | Purpose |
|---|---|
| `POST /api/login`, `POST /api/logout`, `GET /api/me` | Session sign-in |
| `GET /api/demo/accounts`, `POST /api/demo/login` | Persona sign-in, demo mode only (404 otherwise) |
| `GET /api/departments/risk-summary` | All departments with KRIs, scores, bank totals and data lineage |
| `GET /api/departments/{id}/historical-risk` | Six-month composite trend |
| `GET /api/audit-issues`, `GET /api/audit-issues/{id}` | Findings list (filters, paging) and detail with workflow history |
| `POST /api/audit-issues/{id}/plans`, `POST /api/audit-issues/{id}/evidence` | Unit owner submissions |
| `POST /api/remediation-submissions/{id}/review` | Auditor decision |
| `GET /api/kri-disputes`, `POST /api/kri-disputes`, `GET /api/kri-disputes/{id}` | Disputes list, filing, detail |
| `POST /api/kri-disputes/{id}/review` | IT Risk decision |
| `GET /api/work-queue` | What the signed-in user has to act on |
| `GET /api/servers` | Server inventory (filters, paging) |
| `GET /api/attachments/{id}` | Authorised evidence download |

## Tests

```bash
php artisan test
```

The tests run on in-memory SQLite, so they never touch your development database.
