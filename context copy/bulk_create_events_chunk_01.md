# Bulk Create Events — `POST /api/event-bulk-create-jobs`

## Summary

Create an **asynchronous bulk event job**, sending up to 1,000 events per request for one or more profiles. citeturn1search3turn1search5turn1search0

## Endpoint

- **URL:** `https://a.klaviyo.com/api/event-bulk-create-jobs`
- **Method:** `POST`
- **Execution model:**  
  - Request itself is **synchronous** and returns an event‑bulk‑create job resource.  
  - Event processing is **asynchronous**; events are ingested after the job runs. citeturn1search5turn1search3

## Authentication & Scopes

- **Auth:** Private key or OAuth.
- **Scopes:** `events:write`. citeturn1search3turn1search0

## Identifiers & Relationships

Each event in the bulk set follows the same identifier rules as `POST /api/events`: citeturn1search3turn1search2turn1search5

- **Metric:** must include metric `name`.
- **Profile:** must include at least one identifier (`id`, `email`, `phone_number`, or `external_id` as part of profile attributes).

This endpoint can also create/update profiles based on supplied profile attributes.

## Request Schema (high‑level)

- `data.type` = `"event-bulk-create-job"`
- `data.attributes.events-bulk-create.data` = array of `"event-bulk-create"` resources, each containing:
  - Event attributes (properties, time)
  - Metric relationship
  - Profile relationship and attributes. citeturn1search5turn1search3turn1search19

## Rate Limits & Batch Limits

From Bulk Create Events reference: citeturn1search3turn1search0

- **Max events per request:** `1000`
- **Rate limits:**
  - **Burst:** `10/s`
  - **Steady:** `150/m`

## Ordering & Dependencies

- Ideal for **historical backfills** and nightly syncs where you have many events to ingest.
- Typical workflow:
  1. Ensure profiles (or at least identifiers) exist or will be created by event payloads.
  2. POST `event-bulk-create-jobs` with up to 1000 events.
  3. Monitor job status via events‑job endpoints (depending on revision; pattern is similar to profile bulk jobs). citeturn1search5turn1search22
- Do not rely on events being **immediately** visible in analytics/flows; treat them as eventually consistent.

## Failure Modes & Gotchas

- If validation fails for the job request, you receive **4xx** and the job is not created.
- Once the job is created, per‑event errors are reported via job error resources (see revision‑specific docs / Postman collection). citeturn1search5turn1search25
- For server‑side applications, **do not use** `/client/event-bulk-create`; that endpoint is for client‑side JS only. citeturn1search0turn1search11