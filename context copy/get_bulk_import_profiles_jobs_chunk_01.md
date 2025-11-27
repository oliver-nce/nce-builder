# Get Bulk Import Profiles Jobs — `GET /api/profile-bulk-import-jobs`

## Summary

List **bulk profile import jobs** for the account, up to 100 jobs per request. Used to monitor and debug bulk profile imports. citeturn2search9turn0search1

## Endpoint

- **URL:** `https://a.klaviyo.com/api/profile-bulk-import-jobs`
- **Method:** `GET`
- **Execution model:** **Synchronous** — returns current job metadata only.

## Authentication & Scopes

- **Auth:** Private key or OAuth token. citeturn0search7  
- **Required scopes:** `lists:read`, `profiles:read`. citeturn2search9

## Request Parameters (high‑level)

- Query parameters for filtering/sorting: citeturn0search1turn2search9
  - `filter=any(status,["queued","processing"])` to get active jobs
  - Pagination parameters per JSON:API conventions (`page[size]`, `page[cursor]` etc., depending on revision)

## Response Schema (high‑level)

- `data` = array of job resources:
  - `id` (job ID)
  - `type` = `"profile-bulk-import-job"`
  - `attributes` including:
    - `status` (`queued`, `processing`, `completed`, `failed`)
    - timestamps, counts, and metadata

## Rate Limits

From the reference: citeturn2search9

- **Burst:** `10/s`
- **Steady:** `150/m`

## Ordering & Dependencies

- Use this endpoint when you need to **discover all recent jobs**, then inspect them individually via:
  - `GET /api/profile-bulk-import-jobs/{job_id}` (single job) citeturn2search11
  - Errors / profiles / lists sub‑resources. citeturn2search2turn2search14turn2search26
- Typical pattern in automation:
  1. POST `profile-bulk-import-jobs` to create a job.
  2. Periodically call this endpoint filtered by `status in ["queued","processing"]` to find jobs still running.
  3. When `status=completed`, query detailed results.

## Failure Modes & Gotchas

- If your query uses invalid filter syntax, you’ll get **4xx** errors.
- The endpoint only returns up to **100 jobs per request**; you must paginate for larger histories. citeturn2search9turn0search1