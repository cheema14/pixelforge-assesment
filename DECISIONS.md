# Decisions

The brief intentionally left several details open. This file records each one, the decision taken, and the reasoning. Everything here is implemented in the codebase and covered by the test suite.

---

## 1. Money representation and rounding

**Decision:** every amount is an integer number of **nanodollars** (`1 USD = 1_000_000_000`). Pixelforge quotes costs to nine decimal places and rates per million tokens, so nanodollars represent every provider amount exactly.

- Token costs use half-up rounding at the nanodollar: `cost = (tokens × rate_nano + 500_000) ÷ 1_000_000`.
- The 25% markup uses the same half-up rule on the provider cost: `(cost × 125 + 50) ÷ 100`.
- Display rounds to six decimals by default and trims trailing zeroes, but never drops below cents.
- The Vue preview (`resources/js/lib/money.ts`) mirrors this math with `BigInt`, so the number the user sees before submitting is the number PHP calculates.

**Why:** floats drift at the sixth decimal and the brief explicitly calls out sub-cent costs. Integers also make ledger sums exact and comparisons safe.

**Trade-off:** all arithmetic must stay inside `BIGINT`; amounts are validated/limited to keep that true. `Money` is the only place that converts decimals.

---

## 2. Pricing a generation before it completes

**Decision:** the up-front price is an **estimate built from the live `GET /v1/pricing` table**:

| Component | How it is estimated |
| --- | --- |
| Input text | `ceil(characters ÷ characters_per_text_token)`, exact for the submitted prompt |
| Reference images | `per_reference_image` tokens per URL (765 in the current table) |
| Output | exact token count for the chosen quality × size |
| Reasoning | **midpoint** of the documented min/max for the chosen quality |
| Worst case | text at `+15%`, reasoning at the documented **maximum** |

The UI shows both the estimated charge and the worst-case ceiling. The reasoning midpoint is also what gets reserved.

**Why:** output tokens are exact and text tokens are known, but reasoning tokens are only known by the provider during/after generation. The midpoint is the fairest single-number estimate; showing the ceiling separately keeps the promise of "up to" honest.

**Fallback:** if pricing is unreachable, the bundled table in `config/pixelforge.php` is used **for estimates only**. Actual billing always uses the `rates` returned with the generation, so a stale fallback can never move money.

---

## 3. Charging semantics (reserve → settle)

**Decision:** a generation is a two-step ledger operation.

1. **Reserve** the estimate when the generation is created (`generation_reserve`, negative).
2. On success, **settle** at the real cost: `adjustment = reserved − charged` (`generation_settlement`, positive refund or negative top-up).
3. On failure, **refund** the entire reservation (`generation_refund`) and record `charged_nano = 0`.

A generation is settled exactly once: `settled_at` is written in the same transaction as the ledger entry, and every settle path returns early when it is already set.

**Why:** reserving before the provider call prevents a user from spending the same credits in two concurrent requests; settling afterwards avoids charging for tokens that were never used. Every number the user saw is explained by a ledger row.

### The deliberately open case: the actual cost can exceed the estimate

The reservation is the midpoint estimate. If reasoning runs to the maximum, the real charge can exceed it. There are two honest options:

- **Chosen:** allow the settlement to go negative. The user is never blocked mid-generation, can end slightly below zero, and the next generation is **blocked** because the affordability check reads the live balance. This keeps the user experience smooth and the accounting exact.
- Rejected: reserve the worst case. This ties up significantly more credit than most generations need and makes the balance look lower than it is.

The consequence is documented in the UI ("up to $X if reasoning runs long") and in the credits page. No generation is ever started unless the *estimate* is affordable.

### Failed generations

Per the brief, a failed generation costs the user nothing. The provider cost is recorded as zero and the reservation is refunded in full, even if Pixelforge internally consumed some tokens. If the provider does bill failed work, the admin report will show that difference explicitly (see §5) instead of hiding it.

---

## 4. Provider cost source of truth

**Decision:** provider cost is recomputed by this application from the **usage and rates returned with the generation**, using the same published rate table, rather than trusting a single "cost" field.

**Why:** the brief warns that part of the provider cost (reasoning tokens) is unknown until completion. Recomputing from usage guarantees the app's recorded cost is directly explainable and comparable to `GET /v1/usage`. If Pixelforge's own total ever disagrees, the report shows both numbers and their difference.

`Money::providerCost()` requires all four billed token types; a missing rate is treated as a bug and throws rather than silently under-billing.

---

## 5. Admin report reconciliation

**Decision:** the report joins two sources per UTC day:

- **Local:** succeeded, settled generations grouped by `completed_at` (credit spend, computed provider cost, profit = charged − provider cost, per user).
- **Provider:** `GET /v1/usage` for the same range, also per day.

Each day includes `differenceNano = local provider cost − provider usage` and a `reconciled` flag. The page also shows the total difference and an explicit warning when it is non-zero.

**Why UTC:** the provider's usage days are date strings; pinning everything to UTC makes the join deterministic regardless of the user's or server's timezone.

**Range:** defaults to the last 30 days, validated to at most 92 days (the provider's documented maximum), and the form rejects inverted ranges.

**Only settled succeeded generations count.** Active, failed or somehow-settled-but-open rows cannot represent revenue, so including them would inflate profit.

**Why a difference can legitimately exist:**

- The usage endpoint is scoped to the API key. Sharing a key with another environment adds provider costs this database did not record (this deployment shares one key — see §14). The report says this on the page.
- A generation completed but has not been reconciled yet (webhook delayed) — the poller fixes this within 15 seconds.
- Failed generations where the provider still billed usage.

I deliberately **surface** the difference instead of suppressing it; a report that always agrees with itself is not a reconciliation.

Usage is cached for five minutes to keep the page fast; pricing is cached for an hour.

---

## 6. Webhooks

### Signature verification

**Decision:** Pixelforge signs `"{timestamp}.{raw body}"` with HMAC-SHA256 and sends:

```
Pixelforge-Signature: t=<unix>,v1=<hex>
```

The middleware requires a numeric timestamp within **300 seconds**, recomputes the digest over the *raw* request body, and compares with `hash_equals` (constant time). Missing/invalid headers return `400`; an unconfigured secret returns `500` (fail closed) and logs an error. The route is exempt from CSRF because the caller is a provider, not a browser session — the signature is the authentication.

**Why:** timestamp tolerance limits replay windows, constant-time comparison avoids timing leaks, and raw-body hashing matters because re-encoding JSON would change whitespace and invalidate the signature.

### Duplicate deliveries

**Decision:** the event id is a unique key in `webhook_events`. The row is inserted with `insertOrIgnore` **inside the same database transaction** that applies the event. If the insert returns 0 (already seen), the handler returns without touching the generation or the ledger. If applying the event throws, the transaction rolls back — including the dedupe row — so the provider's retry is processed instead of being swallowed.

**Why:** "seen this id" and "applied this event" must be atomic; either both happen or neither does. This is also why the dedupe row carries the generation id and a `processed_at` timestamp for auditing.

### Out-of-order events

**Decision:** statuses have a forward-only rank (`queued = 0`, `processing = 1`, `succeeded = failed = 2`). An event can only move a generation forward. Once a generation is terminal **and settled**, every later snapshot is ignored, regardless of type.

**Why:** provider events can arrive in any order and may be replayed. Terminal-success-then-processing must not reopen the generation, and a late success must not overwrite a recorded failure (failures are also what triggered the refund). Successful and failed share the top rank on purpose.

Snapshots whose provider id disagrees with the locally stored `pixelforge_id` are ignored with a warning; the provider id is only attached when the local one is null.

### HTTP responses

| Situation | Response | Reason |
| --- | --- | --- |
| Valid event (including duplicates) | `204` | Acked; retrying adds nothing |
| `ping`, unknown event types | `204`, not stored | Not part of generation state |
| Event for a generation we never created | `204`, stored as processed | Retrying would never help |
| Malformed event / missing object | `400` | Provider bug; visible in logs |
| Bad signature / stale timestamp | `400`, not stored | Rejected |

Unknown generations are recorded (with a null generation id) rather than silently dropped, so the admin can see provider events that match nothing.

### Finding the generation

The submit call includes `metadata.app_generation_id` (the ULID). The handler first matches on that, then falls back to `pixelforge_id`. This survives the case where the create call timed out after the provider accepted it but before the id was stored.

---

## 7. Polling as a backup

**Decision:** webhooks are the primary mechanism, but a self-chaining `ReconcileGeneration` job polls `GET /generations/{id}` every 15 seconds while a generation is active, and a scheduled command re-dispatches stale work every minute.

- Processing for more than **15 minutes** → failed + refunded (`processing_timeout`).
- Provider returns **404** → failed + refunded (`generation_not_found`).
- Retryable provider errors (429/5xx/connection) → the job is retried by the queue (3 attempts); the generation stays open.
- A generation that succeeded but is missing billable usage stays unsettled and keeps being polled instead of charging a guess.
- Generations stuck without a provider id for more than **2 minutes** are re-submitted (safe because the idempotency key is the generation ULID).

**Why not rely on webhooks alone:** delivery failures happen. Polling makes final state converge even if every webhook is lost.

**Implementation note:** `ReconcileGeneration` deliberately does **not** implement `ShouldBeUnique`. Laravel holds the unique lock until the job finishes, so a self-re-dispatch would be dropped while the lock is still held, silently stopping the chain after one iteration. `SubmitGeneration`, which never re-dispatches itself, remains unique per generation.

---

## 8. History, live status and image expiry

**Decision:** the provider's image URL and expiry timestamp are stored as-is; the image is not downloaded or re-hosted.

- While any generation is active, the page reloads only the `generations` prop every 5 seconds.
- When `image_url_expires_at` is in the past, the page shows an explicit "expired" placeholder instead of a broken image.

**Why:** re-hosting every output adds storage cost and a copy of provider content, which the brief did not ask for. Polling is simpler and more robust than broadcasting for a page that changes at most every few seconds.

**Trade-off:** images disappear when Pixelforge's URL expires. The expiry is visible, and persisting to object storage would be the natural next step if images must be permanent.

---

## 9. Credits and balances

**Decision:**

- Balance is the sum of `credit_transactions` for the user — no mutable balance column to drift.
- Every user starts with a **$5 welcome credit**; the seeder adds a **$25 demo purchase** per demo account.
- "Buy credits" is an explicit **fake purchase** capped at $1–$500, written straight to the ledger. There is no payment provider, so no card data, webhooks or refunds exist for purchases.
- Creation is blocked with a validation error when the balance cannot cover the **estimate**.
- The ledger writes happen inside the same transaction as the generation row and under a `lockForUpdate` on the user, so two simultaneous submissions cannot both pass the balance check for the same credits.

**Why:** the brief asks for balances and blocking, not payments. A ledger-only design is auditable, and locking the user row makes the affordability check meaningful under concurrency.

**Deliberately not done:** purchase idempotency keys or provider purchase webhooks. With real money those become mandatory; here they would be ceremony.

---

## 10. Access control

- Every generation and ledger query is scoped through the authenticated user's relationships (`$request->user()->generations()`), so one user cannot read another's data by guessing ids.
- Admin report access uses a `view-admin-reports` Gate backed by the `users.is_admin` boolean; the Form Request authorizes and the route applies `can:`.
- Create and purchase endpoints are rate-limited to 10 requests per minute per user (falling back to IP), which protects both the app and the Pixelforge key.
- The API key is read from configuration only, never shipped to the browser; `.env` is gitignored and `.env.example` is blank.

---

## 11. Data model

- `generations` has ULIDs. The id is sent to Pixelforge as the idempotency key and in `metadata`, so a retry cannot create a second provider generation.
- `users → generations` cascades on delete, and ledger rows cascade on `user_id`, so removing an account never leaves orphaned financial data. Deleting a single **generation** instead nulls `generation_id` on its ledger and webhook rows (`nullOnDelete`), preserving the audit trail.
- `pixelforge_id` is nullable and unique: null until the provider accepts the job.
- Timestamps are cast to immutable datetimes; money uses `unsignedBigInteger` columns except the ledger's signed `amount_nano`.
- Pricing snapshots (`rates`, `usage`) are stored as JSON on the generation, so a later provider price change can never rewrite history.
- Indexes cover the two hot queries: user + created_at for history, status + settled_at for polling.

---

## 12. Operational choices

- Queue, cache and sessions use database drivers by default so the app runs with nothing but PHP and SQLite. Production can switch to Redis without code changes.
- The scheduler runs `pixelforge:reconcile` every minute with `withoutOverlapping`.
- `Model::preventLazyLoading` is enabled outside production, so accidental N+1 queries fail loudly in development and tests (the admin report eager-loads its users).
- Webhook event payloads are stored verbatim for auditing.

---

## 13. Provider limits, overloads and retries

Pixelforge enforces 60 requests/minute, 3 active generations and 500 generations per UTC day, and can return `429` (`rate_limit_exceeded`, `concurrency_limit_exceeded`, `daily_limit_exceeded`) or `503 overloaded`. `PixelforgeException` classifies every response once, and both jobs act on that classification:

| Provider response | Classification | Behaviour |
| --- | --- | --- |
| `429 rate_limit_exceeded`, `429 concurrency_limit_exceeded`, `503 overloaded`, `5xx`, connection failure | retryable | Submission is released with `Retry-After` when present, otherwise exponential backoff `min(120, 5 × 2^(attempts−1))` — up to 6 attempts |
| `429 daily_limit_exceeded` | non-retryable for today | Fails immediately with `daily_limit_exceeded`, refunds in full; retrying cannot succeed before the UTC day rolls over |
| `400` validation / content policy / idempotency reuse, `401` bad key, `404` | non-retryable | Fails immediately with the provider's code, refunds in full |
| Retries exhausted | — | `failed()` fails and refunds with `submission_unavailable`; nothing is charged |

The same classification governs the poller: retryable read errors are rethrown so the queue retries the job, while `404` means the provider genuinely lost the generation and is finalized as failed + refunded.

**Why fail fast on the daily quota:** it is a shared budget that only resets at UTC midnight. Retrying it burns attempts and delays the user's answer, and holding the reservation would freeze credits for a generation that cannot start. A refund with the exact provider code is the honest outcome, and the user can retry after the reset.

**Why the concurrency limit is retried rather than surfaced:** it is temporary and caused by the app's own parallel submissions, not by anything the user did. I verified this live by submitting four generations at once: the fourth submission was rejected, released, and succeeded about 14 seconds later, and all four settled correctly with no failed jobs.

---

## 14. One API key for development and production

**Decision:** both environments use the same Pixelforge key, because only one key exists for this assessment.

The consequence is that `GET /v1/usage` for a given day includes generations created during local testing. The admin report compares that provider number against this database's own settled generations and shows the difference per day with a warning, so the mismatch is visible and explainable rather than hidden. A separate key per environment would make the two numbers line up exactly — the config comment recommends that, and it is the first thing to change with a real deployment.

---

## 15. What I would add for real money and scale

1. A payment provider with signed purchase webhooks and idempotent top-ups.
2. A chosen negative-balance policy enforced at purchase time (e.g. block new generations while negative, or collect the overage).
3. Object storage for generated images so history survives provider URL expiry.
4. Horizon (or Cloud's queue metrics) with failure alerting on stuck settlements.
5. A subscription/billing domain separated from the demo credit purchase, plus receipts and tax handling.
6. Consider reconciling provider-billed failed generations separately if Pixelforge's contract changes the "failures are free" rule.

---

## 16. Configuration reference

| Key | Default | Purpose |
| --- | --- | --- |
| `pixelforge.markup_percent` | `25` | Customer markup over provider cost |
| `pixelforge.welcome_credit_nano` | `$5.00` | Credit granted at registration |
| `pixelforge.pricing_cache_seconds` | `3600` | Pricing cache lifetime |
| `pixelforge.provider_usage_cache_seconds` | `300` | Admin usage cache lifetime |
| `pixelforge.reconcile_interval_seconds` | `15` | Poll cadence per active generation |
| `pixelforge.max_processing_minutes` | `15` | Before a generation is timed out and refunded |
| `pixelforge.stale_submission_minutes` | `2` | Before a submission without a provider id is retried |
| `pixelforge.webhook_tolerance_seconds` | `300` | Accepted signature clock skew |
