# Frontend

Vue 3 + Vite + Tailwind CSS 4 + Chart.js dashboard for the KRI API.

```bash
npm install
npm run dev      # http://localhost:5173, proxies /api to http://127.0.0.1:8000
npm run build    # production build in dist/
```

Start the Laravel API first (`php artisan serve` in `../backend`). To proxy to another host, set `VITE_API_PROXY_TARGET`. To call the API directly instead of through the proxy, set `VITE_API_BASE_URL`, for example `https://kri.example.com/api`.

## Languages

The UI is available in Indonesian (default) and English, switched with the ID/EN toggle in the top bar and on the sign-in page. The choice is remembered in the browser.

- **Text and formats.** All UI text lives in `src/i18n/id.js` and `src/i18n/en.js` (vue-i18n). Numbers and dates follow the language: `id-ID` or `en-GB`.
- **Charts.** Chart labels are redrawn when the language changes.
- **Server messages.** The SPA sends `Accept-Language`, so server messages (sign-in, validation, workflow errors) come back in the same language.
- **Page language.** `<html lang>` is updated on every switch, which helps screen readers and stops the browser from offering to translate the page.
- **Data stays as recorded.** Department names, audit finding titles and user-written remediation text are record values, so they are not translated.

## Sign-in and demo mode

Every page except `/masuk` requires a session. Authentication uses Laravel Sanctum's cookie-based SPA mode: an HttpOnly session cookie plus CSRF protection (`/sanctum/csrf-cookie`, `X-XSRF-TOKEN`). No token is ever stored in JavaScript-readable storage.

**Demo mode (`KRI_DEMO_MODE=true` in `backend/.env`).** This is the portfolio default.

- **Sign-in.** The sign-in page shows the demo personas instead of a password form. Pick one to sign in, no password needed.
- **Switching.** Once signed in, a **View as** menu in the top bar switches persona in place, so you can walk a finding through every role.
- **Same controls as a real sign-in.** Each persona is a real user with a real session, so roles, maker-checker and the audit trail work exactly as with a normal sign-in.
- **Scope.** The demo endpoints only accept accounts flagged `is_demo`, and they return 404 when demo mode is off.

| Persona | Role | Can do |
|---|---|---|
| Hendra Wijaya | Board of directors | View only |
| Rina Kartika (Kartu Kredit), plus one owner per department | Unit owner | Submit action plans and remediation evidence for their own department |
| Fajar Nugroho | Internal auditor | Approve or return submissions, close findings |
| Maya Anggraini | IT Risk | Reviews KRI data disputes (phase 5b) |

**Demo mode off (`KRI_DEMO_MODE=false`).** This is what a real deployment uses. The page shows only the email and password form. Sign-in is throttled after 5 failed attempts and always gives the same error message, whether the email or the password is wrong. Demo accounts get a random password unless `DEMO_USER_PASSWORD` is set.

## Structure

The app has a sidebar and five pages (Vue Router, history mode). A production host needs a fallback that serves `index.html` for unknown paths.

| Route | Page |
|---|---|
| `/` | Overview: headline (riskiest department and why), bank-wide KPI cards with change since last month, department × KRI heatmap with drill-down dialog, 6-month trend, score composition |
| `/departemen/:id` | One department: score and change, the four KRIs, trend, and the full explanation of its score |
| `/temuan-audit` | All audit findings with filters (department, risk level, status, overdue, search), sorting and pagination. Filters live in the URL, so a filtered view can be shared. |
| `/server` | Server inventory with filters (department, patch compliance, OS, hostname/IP search) |
| `/metodologi` | Weights, thresholds, a worked example and the data lineage (source systems and import log), rendered from the API |
| `/masuk` | Sign-in: persona picker in demo mode, email and password form otherwise |
| `/tindak-lanjut` | Work queue: what the signed-in user has to act on |
| `/temuan-audit/:id` | One finding: remediation steps, the role-specific action form, submission history with evidence files, audit trail |

| Path | Purpose |
|---|---|
| `src/App.vue` | Shell: persistent sidebar on desktop, modal drawer on mobile, top bar, page-title focus on navigation |
| `src/lib/store.js` | Shared risk summary and per-department history cache |
| `src/lib/useListQuery.js` | Server-side list state synced with the URL query string |
| `src/components/RiskHeatmap.vue` | Heatmap table. Every cell carries a status colour **and** a text label with a distinct icon shape. |
| `src/components/DepartmentDetail.vue` | Why a department scores what it does. Shared by the dialog and the department page. |
| `src/components/RiskTrendChart.vue` | Chart.js line chart over Safe / Warning / Critical zones, with a table-view alternative |
| `src/components/ScoreComposition.vue` | Stacked bars of each KRI's contribution, with a validated categorical palette and a table view |
| `src/lib/kri.js`, `src/lib/format.js` | KRI metadata and Indonesian status vocabulary; `id-ID` formatting via `Intl` |
| `src/style.css` | Design tokens: primitives → semantic roles (`--ui-*`), light and dark palettes |

The UI text is in Indonesian, because the intended audience is an Indonesian bank's board of directors.

## Accessibility

Target is WCAG 2.2 AA. The following were checked:

- **Automated audit.** axe-core reports 0 violations on every page, in light and dark mode, and with the drill-down dialog open.
- **Contrast.** Every text token pair was computed at 5.1:1 or higher. Control borders are at least 3:1.
- **Keyboard.** The tab order follows the visual order. The dialog opens with Enter and closes with Esc, and focus returns to the button that opened it.
- **Layout and motion.** No page scrolls horizontally at 320 px or 768 px. `prefers-reduced-motion` is respected.
- **Navigation.** On mobile, the menu drawer closes after navigation. After every page change, focus moves to the new page title.

Screen reader testing has not been done yet.
