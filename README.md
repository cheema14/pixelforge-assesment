# Lumina

Lumina is an AI image studio built with **Laravel 13**, **Inertia v2** and **Vue 3** on top of the **Pixelforge** API. A user writes a prompt, picks a size and quality, optionally attaches reference images, sees an up-front price, and gets the finished image with its exact charge. Credits are reserved before submission and settled at the provider's real token usage.

## Stack

| Layer | Choice |
| --- | --- |
| PHP | 8.4 (composer requires `^8.3`) |
| Framework | Laravel 13 |
| Frontend | Vue 3 + TypeScript + Inertia v2 + Tailwind CSS 3 |
| Auth | Breeze |
| Money | integer nanodollars (`1e-9 USD`) |
| Queues / cache / session | database drivers locally, swap for Redis in production |
| Tests | Pest 4 |

## How a generation works

1. `POST /generations` validates the request (Form Request) and `CreateGeneration` estimates the cost from the live Pixelforge pricing table.
2. The estimate is the **midpoint** of the documented reasoning-token range plus the exact output and reference-image tokens. The UI also shows the **worst case** using the maximum reasoning tokens.
3. The user's balance is locked, checked, and the estimated charge is written to the credit ledger as a reservation.
4. `SubmitGeneration` posts to Pixelforge with the generation ULID as its `Idempotency-Key`, then schedules a `ReconcileGeneration` poll.
5. Status updates arrive through **signed webhooks** and, as a backup, through **polling** every 15 seconds.
6. On success the actual provider cost is recomputed from the usage and rates returned with the generation, the 25% markup is applied, and the reservation is settled. On failure the reservation is refunded in full.

```
queued ──▶ processing ──▶ succeeded (settled)
                     └──▶ failed    (refunded)
```

## Local setup

```bash
composer install
cp .env.example .env                 # if .env does not exist yet
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build

composer run dev                     # serves the app, queue worker and Vite
```

`composer run dev` uses `php artisan dev`, which starts everything in one process group. If you prefer separate terminals:

```bash
php artisan serve
php artisan queue:work
npm run dev
```

The webhook secret and API key go in `.env`:

```dotenv
PIXELFORGE_BASE_URL=https://pixelforge.promptedit.dev/v1
PIXELFORGE_API_KEY=pf_...
PIXELFORGE_WEBHOOK_SECRET=whsec_...
```

Nothing else is required — pricing falls back to a bundled table when `GET /v1/pricing` is unreachable, and the fallback is only used for estimates.

### Demo accounts

`php artisan migrate --seed` creates two verified accounts, both with the password `password`:

| Account | Access |
| --- | --- |
| `admin@lumina.test` | everything, including the admin report |
| `demo@lumina.test` | regular studio account |

Each account starts with a **$5 welcome credit** and a **$25 demo purchase**. The "Buy credits" flow is fake by design; it writes to the ledger immediately.

## Features

- **Create** — prompt, size (square / landscape / portrait), quality, up to four public reference image URLs, a live estimate with the 25% markup and a worst-case ceiling, and a clear balance warning when the generation is unaffordable.
- **History** — every generation with its status, estimated and actual cost, the image, and an "expired" placeholder once Pixelforge's URL stops working. Active generations refresh every five seconds.
- **Credits** — current balance, demo purchases, and the full ledger (reservations, settlements, refunds, purchases).
- **Admin report** — credit spend, provider cost, profit and Pixelforge `/v1/usage` totals per UTC day and per user, with the difference surfaced rather than hidden.
- **Webhooks** — HMAC-verified, idempotent, safe against duplicate and out-of-order deliveries.

## Tests and checks

```bash
php artisan test          # 114 Pest tests
vendor/bin/pint --test    # code style
npm run build             # vue-tsc + Vite production build
```

The suite covers the money math, pricing/estimation, balance blocking, reservations, settlement and refunds, webhook signatures and deduplication, out-of-order events, submission failures and retries, polling, the admin report, and page-level authorization.

### Exercising failure modes against the real provider

Pixelforge honours keywords inside the prompt, so the hard paths can be triggered on demand:

| Keyword | What the provider does | What it proves here |
| --- | --- | --- |
| `[fail]` | Accepts the job, then fails while processing | The generation ends `failed`, the reservation is refunded in full, and the ledger shows `No charge` |
| `[slow]` | Adds 60–90 seconds of processing | The webhook/poller keeps the status live and settlement still lands exactly once |
| `[duplicate]` | Delivers every webhook event twice | Deduplication and idempotent ledger writes; the charge happens once (needs the dashboard webhook endpoint) |

I ran `[fail]` and `[slow]` against the live API: a `[fail]` prompt refunded exactly the reservation, a `[slow]` prompt settled 89 seconds after submission, and four concurrent submissions proved the provider's 3-job concurrency limit is retried rather than surfaced as a failure. Duplicate and out-of-order deliveries are covered by feature tests that sign real payloads (see below); `[duplicate]` needs the dashboard webhook secret, so it is exercised once deployment registers the endpoint.

### Receiving webhooks locally

Pixelforge delivers to a public URL, so expose the dev server through a tunnel and register `https://<tunnel-host>/webhooks/pixelforge` in the dashboard with the same value as `PIXELFORGE_WEBHOOK_SECRET`:

```bash
# any one of:
expose share http://localhost:8000
ngrok http 8000
cloudflared tunnel --url http://localhost:8000
```

The dashboard lists every delivery attempt and the response it got, and can resend an event — useful for seeing a duplicate arrive on purpose. Polling still converges if the tunnel drops a delivery.

## Webhooks

Register this URL with Pixelforge, using the same secret as `PIXELFORGE_WEBHOOK_SECRET`:

```
POST https://<your-domain>/webhooks/pixelforge
```

Each request carries:

```
Pixelforge-Signature: t=<unix>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">
```

The middleware rejects anything older than five minutes or with a mismatched signature. Event ids are unique in `webhook_events`, inserted with `insertOrIgnore` in the same transaction that applies the event — a duplicate delivery can never charge twice, and a failed apply rolls the dedupe row back so the provider's retry is processed.

Statuses only move forward (`queued → processing → succeeded/failed`). A terminal, settled generation ignores every later snapshot, so a late success cannot overwrite a recorded failure and an old `processing` event cannot reopen a finished generation.

Polling covers missed webhooks: `pixelforge:reconcile` runs every minute, and each active generation keeps re-scheduling itself every 15 seconds until it settles, hits the 15-minute processing limit (failed + refunded), or disappears from the provider (failed + refunded).

## Admin report

Access is gated by the `view-admin-reports` Gate (`users.is_admin`). Default range is the last 30 days, capped at 92 days to match Pixelforge's usage endpoint. Every completed, settled generation contributes its charged amount, computed provider cost and profit; `/v1/usage` is fetched for the same range and shown side by side. If the numbers differ, the page says so instead of hiding it.

**Use a separate Pixelforge API key per environment where possible.** The usage report is scoped to the key. This deployment runs one key for both local testing and production, so `/v1/usage` also counts generations created during local testing — the report shows that difference explicitly rather than hiding it. This is also why the seeder does not create fake generations.

## Project map

```
app/Actions/Generations/CreateGeneration.php      reserve + queue a generation
app/Actions/Generations/ApplyProviderSnapshot.php settle, refund, never move backwards
app/Actions/ProcessPixelforgeWebhook.php          verify-agnostic, idempotent event handling
app/Services/CreditLedger.php                     the only writer to credit_transactions
app/Services/Pixelforge/                          HTTP client, pricing, value objects
app/Services/AdminReportService.php               spend / provider cost / profit report
app/Http/Middleware/VerifyPixelforgeSignature.php signature + tolerance check
app/Jobs/SubmitGeneration.php                     idempotent submission with retries
app/Jobs/ReconcileGeneration.php                  self-chaining backup poller
app/Console/Commands/…                            dispatches stale work every minute
resources/js/Pages/                               Create, Index, Credits, Admin report
resources/js/lib/money.ts                         BigInt mirror of the PHP money math
```

## Deployment

The app is a normal Laravel application; the repository has no host-specific configuration files.

1. Create a Laravel Cloud (or other PHP 8.4) application from this repository.
2. Build command: `composer install --no-dev --optimize-autoloader && npm ci && npm run build`.
3. Release command: `php artisan migrate --force`.
4. Add a queue worker (`php artisan queue:work --tries=3`) and the scheduler (`php artisan schedule:run` every minute).
5. Set `APP_KEY`, `APP_URL`, database credentials, `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET`.
6. Point the Pixelforge webhook at `https://<domain>/webhooks/pixelforge`.
7. Optionally seed the reviewer accounts with `php artisan db:seed --force` (skip on a public production instance).

## Open details

The brief deliberately left several behaviours open. They are listed, with the decision taken and the reasoning, in [DECISIONS.md](DECISIONS.md).
