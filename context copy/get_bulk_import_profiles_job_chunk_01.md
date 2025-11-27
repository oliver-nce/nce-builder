# Get Bulk Import Profiles Job — `GET /api/profile-bulk-import-jobs/{job_id}`

## Summary

Retrieve a **single bulk profile import job** by job ID, including status and aggregate counts. citeturn2search11turn0search1

## Endpoint

- **URL:** `https://a.klaviyo.com/api/profile-bulk-import-jobs/{job_id}`
- **Method:** `GET`
- **Execution model:** **Synchronous** — returns current state of the job.

## Authentication & Scopes

- **Auth:** Private key or OAuth.
- **Scopes:** `lists:read`, `profiles:read`. citeturn2search11

## Path Parameters

- `job_id` — the ID returned from `POST /api/profile-bulk-import-jobs` when the job was created. citeturn2search3turn2search11

## Response Schema (high‑level)

- `data.id` — job ID
- `data.type` = `"profile-bulk-import-job"`
- `data.attributes`:
  - `status` — `queued`, `processing`, `completed`, `failed` …
  - `created_at`, `updated_at`
  - aggregate counts (submitted, succeeded, failed, etc.) where available. citeturn0search1turn2search11

## Rate Limits

- **Burst:** `10/s`
- **Steady:** `150/m`. citeturn2search11

## Ordering & Dependencies

- This endpoint is central for **polling** job status:
  - Call repeatedly until `status` is `completed` or `failed`.
  - Once completed:
    - Fetch errors via `GET /api/profile-bulk-import-jobs/{id}/import-errors`. citeturn2search2turn2search29
    - Fetch resulting profiles via `GET /api/profile-bulk-import-jobs/{id}/profiles` or profile relationships. citeturn2search14turn2search19turn2search22
- Downstream systems (e.g., events, custom objects) should treat bulk‑imported profiles as **not reliably present** until this job reports `status=completed`.

## Failure Modes & Gotchas

- If an invalid or unknown `job_id` is supplied, the endpoint returns **404**.
- This endpoint reports the **current state** of profiles at read time, not a snapshot of their state at import time; use errors and profile lists to debug individual records. citeturn0search1turn2search1