# Get Profiles for Bulk Import Profiles Job — `GET /api/profile-bulk-import-jobs/{id}/profiles`

## Summary

Return the **profiles successfully processed** by a given bulk profile import job. Useful for validating which records were actually ingested. citeturn2search14turn2search19

## Endpoint

- **URL:** `https://a.klaviyo.com/api/profile-bulk-import-jobs/{id}/profiles`
- **Method:** `GET`
- **Execution model:** **Synchronous** — reads current profile state for profiles associated with the job.

## Authentication & Scopes

- **Auth:** Private key or OAuth.
- **Scopes:** `profiles:read`. citeturn2search14turn2search19

## Path Parameters

- `id` — ID of the bulk import job created earlier. citeturn2search3turn2search11turn2search14

## Response Schema (high‑level)

- `data` — array of profile resources as of **current time**:
  - `id`, `type="profile"`
  - `attributes` — profile attributes (may have changed since import). citeturn0search1turn2search14

> Note: The profiles returned show their **current state**, not necessarily the exact state at the time of import. citeturn0search1

## Rate Limits

- **Burst:** `10/s`
- **Steady:** `150/m`. citeturn2search14turn2search19

## Ordering & Dependencies

- Call only after the import job has **completed**; otherwise, the set of profiles may be incomplete.
- In some workflows you might prefer `GET /api/profile-bulk-import-jobs/{id}/relationships/profiles` to work with just relationships/IDs and then fetch profiles separately. citeturn2search22turn2search20

## Failure Modes & Gotchas

- If the job `id` is invalid, you’ll receive a **404**.
- If you do another import that updates the same profiles, subsequent reads from this endpoint will reflect newer data (since it exposes current state).