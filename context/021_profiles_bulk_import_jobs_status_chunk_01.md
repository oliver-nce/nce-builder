# Profiles API – Bulk Import Jobs Status Endpoints

## Get Bulk Import Profiles Jobs (`GET /api/profile-bulk-import-jobs`)

- **Method:** `GET`
- **URL:** `https://a.klaviyo.com/api/profile-bulk-import-jobs`
- **Purpose:** Retrieve bulk profile import jobs for the account.
- **Synchronous / Asynchronous:** Synchronous read.

### Required Headers

- `Authorization: Klaviyo-API-Key {YOUR_PRIVATE_API_KEY}`
- `Accept: application/vnd.api+json`
- `revision: {SUPPORTED_REVISION}`

### Behavior

- Returns up to **100 jobs per request**.
- Each job resource includes:
  - `id`: job ID.
  - `attributes.status`: `queued`, `processing`, `complete`, or `cancelled`.
  - Additional fields such as record counts and timestamps, depending on revision.

### Pagination

- Uses JSON:API pagination with `page[size]`, `page[cursor]`, and `links` (`self`, `next`, `prev`).

### Rate Limits and Scopes

- Rate limits:
  - Burst: `10/s`
  - Steady: `150/m`
- Required scopes:
  - `lists:read`
  - `profiles:read`

### Error Handling

- Invalid query parameters or pagination cursors return validation errors.
- Insufficient scopes yield authorization errors.
- Rate limit violations return HTTP `429`.

**Not specified in Klaviyo documentation:** Automatic cleanup or expiration schedule for completed jobs.

---

## Get Profile IDs for Bulk Import Profiles Job (`GET /api/profile-bulk-import-jobs/{id}/relationships/profiles`)

- **Method:** `GET`
- **URL Template:** `https://a.klaviyo.com/api/profile-bulk-import-jobs/{id}/relationships/profiles`
- **Purpose:** Retrieve the profile relationships associated with a bulk import job.
- **Synchronous / Asynchronous:** Synchronous read.

### Required Headers

- `Authorization: Klaviyo-API-Key {YOUR_PRIVATE_API_KEY}`
- `Accept: application/vnd.api+json`
- `revision: {SUPPORTED_REVISION}`

### Behavior

- Returns a JSON:API relationships document with:
  - `data`: array of identifiers with `type: "profile"` and `id` values.
  - `links`: `self` and pagination links where applicable.

### Rate Limits and Scopes

- Rate limits:
  - Burst: `10/s`
  - Steady: `150/m`
- Required scopes:
  - `profiles:read`

### Error Handling

- Unknown or inaccessible job IDs return `404 Not Found`.
- Rate limit violations return HTTP `429`.

**Not specified in Klaviyo documentation:** Whether the relationship set includes only successfully imported profiles or any failed records as well; details for failures are available from job status endpoints.
