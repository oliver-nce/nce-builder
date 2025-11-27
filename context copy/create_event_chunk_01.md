# Create Event — `POST /api/events`

## Summary

Create a **server‑side event** representing an action taken by or for a profile. This endpoint can also create or update the associated profile as part of the request. citeturn1search2turn1search5

## Endpoint

- **URL:** `https://a.klaviyo.com/api/events`
- **Method:** `POST`
- **Execution model:**  
  - Request is **synchronous** for validation and enqueueing.  
  - Event processing is **asynchronous**: a successful response means the event was accepted and scheduled, **not** that processing is complete. citeturn1search12turn1search8

## Authentication & Scopes

- **Auth:** Server‑side; private API key or OAuth token. citeturn0search7  
- **Scopes:** `events:write`. citeturn1search12turn1search2

## Identifiers & Relationships

At minimum, each event must have: citeturn1search2turn1search5

- **Metric**:
  - Metric `name` (e.g. `"Registered Session"`, `"Placed Order"`).
- **Profile**:
  - At least one profile identifier inside the `profile` object: `id`, `email`, or `phone_number` (or `external_id` when using profile attributes).

The event resource uses JSON:API `relationships` for:

- `metric` → event type
- `profile` → associated profile

Klaviyo can create/update the profile inline based on supplied identifiers and profile attributes. citeturn1search2turn2search10

## Request Schema (high‑level)

- `data.type` = `"event"`
- `data.attributes`:
  - `properties` — event‑specific attributes
  - `time` / `datetime` — when the event occurred
- `data.relationships.metric.data`:
  - `type` = `"metric"`
  - `attributes.name` = metric name
- `data.relationships.profile.data`:
  - `type` = `"profile"`
  - `attributes` including identifiers and optional properties. citeturn1search2turn1search5turn0search23

## Rate Limits

From the Create Event reference: citeturn1search12

- **Burst:** `350/s`
- **Steady:** `3500/m`

## Ordering & Dependencies

- You can safely call `POST /api/events` **immediately after** creating or updating a profile; identifiers will resolve as long as they match. citeturn1search6turn0search23
- For high‑volume backfills, prefer **Bulk Create Events** (`POST /api/event-bulk-create-jobs`). citeturn1search3turn1search5
- Event ingestion is **asynchronous** inside Klaviyo. There can be a slight delay before the event appears in the UI or is picked up by flows/segments. citeturn1search12turn1search8

## Failure Modes & Gotchas

- If required identifiers are missing (no metric name, no profile identifier), the API returns **4xx** with validation errors. citeturn1search2turn1search5
- Timestamps must be in an acceptable datetime format; invalid dates cause errors. citeturn1search10
- For client‑side usage, use **Create Client Event** (`POST /client/events`) instead of this server‑side endpoint. citeturn1search23turn1search0