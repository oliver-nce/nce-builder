# Bulk Import Profiles — Spawn Job — `POST /api/profile-bulk-import-jobs`

## Summary

Create an **asynchronous bulk profile import job** that **upserts** up to 10,000 profiles per request, optionally linking them to a list. citeturn2search3turn2search5

## Endpoint

- **URL:** `https://a.klaviyo.com/api/profile-bulk-import-jobs`
- **Method:** `POST`
- **Execution model:**  
  - Request itself is **synchronous** (returns a **job resource**).  
  - **Profile processing is asynchronous**; profiles are created/updated **after** the job runs. citeturn0search1turn2search5

## Authentication & Scopes

- **Auth:** Server‑side; private key or OAuth token. citeturn0search7  
- **Required scopes:** `lists:write`, `profiles:write`. citeturn2search3turn2search5

## Identifiers & Upsert Behavior

Each profile in the job is an **UPSERT**: citeturn2search5

- Matching is based on standard identifiers in each profile object:
  - `email`
  - `phone_number`
  - `external_id`
- If a match is found → profile is **updated** (only non‑null fields are updated).
- If no match is found → a **new profile** is created.

> **Important:** Bulk Import *does not support* setting fields to `null` to clear them; to clear fields, use the single‑profile Create/Update endpoint (`POST /api/profile-import`). citeturn2search5turn2search27

## Request Schema (high‑level)

Top‑level:

- `data.type` = `"profile-bulk-import-job"`
- `data.attributes`:
  - `profiles` → array of profile objects
  - Optional `list_id` to auto‑associate profiles to a list. citeturn0search1turn2search5

Profile objects resemble single‑profile payloads:

- Identifiers: `email`, `phone_number`, `external_id`
- Profile data: `first_name`, `last_name`, `location`, `properties`, etc. citeturn2search5turn2search10

## Rate Limits & Batch Limits

From Bulk Import Profiles reference: citeturn2search3turn2search5

- **Max profiles per request:** `10,000`
- **Max payload size:** `5MB` total, `100KB` per profile
- **Rate limits:**
  - **Burst:** `10/s`
  - **Steady:** `150/m`

## Ordering & Dependencies

- This endpoint is used for **large backfills or nightly syncs**.
- After job creation, you **must poll** the job endpoints:
  - `GET /api/profile-bulk-import-jobs` (list jobs) citeturn2search9
  - `GET /api/profile-bulk-import-jobs/{job_id}` (single job) citeturn2search11
  - `GET /api/profile-bulk-import-jobs/{id}/import-errors` (errors) citeturn2search2turn2search29
  - `GET /api/profile-bulk-import-jobs/{id}/profiles` or `/relationships/profiles` (resulting profiles). citeturn2search14turn2search22
- Do **not** send dependent events/objects that rely on these profiles until the job `status` is `completed`. citeturn0search1turn2search5

## Failure Modes & Gotchas

- If **synchronous validation** (e.g., malformed email) fails for the batch, the job is **not created** and the endpoint returns a **4xx**. citeturn0search1turn2search8
- Import errors for individual profiles (e.g., invalid field types) appear in the **Import Errors** endpoint **after** the job runs.
- Because null clearing is not supported, stale data must be explicitly cleared via `POST /api/profile-import` after the bulk run if you need hard resets. citeturn2search5turn2search27