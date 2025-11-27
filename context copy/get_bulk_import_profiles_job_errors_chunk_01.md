# Get Errors for Bulk Import Profiles Job — `GET /api/profile-bulk-import-jobs/{id}/import-errors`

## Summary

Retrieve **per‑record errors** for a specific bulk profile import job, used for diagnosing why certain profiles failed to import or update. citeturn2search2turn2search29

## Endpoint

- **URL:** `https://a.klaviyo.com/api/profile-bulk-import-jobs/{id}/import-errors`
- **Method:** `GET`
- **Execution model:** **Synchronous** — reads stored error records for a completed (or failed) job.

## Authentication & Scopes

- **Auth:** Private key or OAuth.
- **Scopes:** `profiles:read`. citeturn2search2turn2search29

## Path Parameters

- `id` — job ID of the bulk import job created via `POST /api/profile-bulk-import-jobs`. citeturn2search3turn2search11turn2search29

## Response Schema (high‑level)

- `data` — array of error resources. Each entry typically contains:
  - Pointer to offending profile record in the original payload (e.g., JSON pointer or index). citeturn2search8turn2search29
  - Error `detail` message (e.g., invalid email format, missing identifier, field type mismatch).
  - Associated timestamps and identifiers where available.

## Rate Limits

- **Burst:** `10/s`
- **Steady:** `150/m`. citeturn2search2turn2search29

## Ordering & Dependencies

- Only meaningful **after** the job finished processing; otherwise, the error list might be incomplete or empty.
- Typical debugging flow:
  1. Create job with `POST /api/profile-bulk-import-jobs`.
  2. Poll `GET /api/profile-bulk-import-jobs/{job_id}` until `status` is `completed` or `failed`. citeturn2search11turn0search1
  3. Call this endpoint to inspect any per‑record errors.
  4. Fix the source data and re‑submit failed records.

## Failure Modes & Gotchas

- For very large jobs, the number of error records can be significant; use pagination where supported.
- If you query errors before the job has finished, you may see a **partial** or empty list even though some records will eventually fail.