# Deployment

The app ships as one Docker image. nginx serves the Vue build and forwards `/api`, `/sanctum`, and `/up` to PHP-FPM (Laravel). The SPA and the API share an origin, so Sanctum's session cookie works without any CORS setup.

The image is set up as a **public demo**: it uses SQLite inside the container and rebuilds and re-seeds the database every time the container starts. Workflow changes and uploaded files last only until the next restart.

## Run it locally

```bash
docker compose up --build
```

Open http://localhost:8080 and pick a demo account. Startup (migrate + seed) takes a few seconds.

## Deploy to Render (free plan)

1. Push the repository to GitHub.
2. In the Render dashboard choose **New → Blueprint** and select the repository. Render reads [`render.yaml`](render.yaml) and creates one Docker web service on the free plan.
3. Apply. The first build takes a few minutes. The app is served at `https://<service-name>.onrender.com`.

You don't need to set any environment variables by hand. At startup, [`docker/entrypoint.sh`](docker/entrypoint.sh):

- generates `APP_KEY` if it is not set;
- sets `APP_URL` and `SANCTUM_STATEFUL_DOMAINS` from `RENDER_EXTERNAL_HOSTNAME`.

`SESSION_SECURE_COOKIE=true` comes from the blueprint.

Free plan behaviour:

- **Sleep.** The service sleeps after about 15 minutes without traffic, and the first request after that waits for it to wake up (roughly a minute).
- **Reset.** Each wake-up is a fresh start, so the demo data resets.
- **Changing terms.** Free-tier terms change from time to time; check Render's pricing page.

## Configuration

| Variable | Default in the image | Notes |
|---|---|---|
| `KRI_RESET_ON_BOOT` | `true` | `migrate:fresh --seed` on start. Set `false` to run plain `migrate` against a persistent database |
| `KRI_DEMO_MODE` | `true` | Account picker on the sign-in page. `false` shows only email and password |
| `DB_CONNECTION` | `sqlite` | Set `mysql` plus `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` to use MySQL/MariaDB (`pdo_mysql` is installed) |
| `APP_KEY` | generated per start | Set a fixed key when sessions must survive a restart |
| `DEMO_USER_PASSWORD` | random | Shared password for the demo accounts when demo mode is off |
| `PORT` | `8080` | Render sets its own |

For a persistent setup, also mount a volume on `/app/backend/storage/app/private` so uploaded evidence survives restarts.

## Security notes

- **Response headers.** nginx sends a strict Content-Security-Policy on the SPA (`script-src 'self'`, no inline scripts or styles), plus `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, and HSTS.
- **Production mode.** `APP_DEBUG=false` and `APP_ENV=production`. Logs go to stderr, so they appear in the platform's log viewer.
- **Proxy trust.** Laravel trusts only the immediate proxy for the client IP and scheme. This keeps secure cookies and the login rate limit working behind Render's TLS proxy.
- **Upload limits.** PHP caps uploads at 6 MB per file and 18 MB per request. The app itself limits attachments to 3 files of up to 5 MB each (PDF, PNG, or JPEG).
