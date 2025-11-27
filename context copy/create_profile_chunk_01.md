# Create Profile — `POST /api/profiles`

## Summary

Create a **new Klaviyo profile** with the given attributes. This endpoint **does not upsert**; it always creates a new profile record (and will fail if constraints are violated). citeturn0search12turn2search19

## Endpoint

- **URL:** `https://a.klaviyo.com/api/profiles`
- **Method:** `POST`
- **Execution model:** **Synchronous request** (validation and write happen inline; response body contains the created profile).

## Authentication & Scopes

- **Auth:** Server‑side; **private API key** or OAuth access token. citeturn0search7  
- **Required scopes:** `profiles:write`. citeturn0search12

## Identifiers

- A profile **does not need a pre‑existing Klaviyo ID**; it is assigned on creation.
- You **should** supply at least one stable identifier so the profile can be related to events/objects later:
  - `email`
  - `phone_number`
  - or a custom `external_id` (in `attributes` → `external_id`). citeturn2search10

## Request Schema (high‑level)

### Required

- Top‑level JSON:  
  - `data.type` = `"profile"`
  - `data.attributes` = object of profile fields. citeturn2search10

### Common optional attributes

Examples (not exhaustive): citeturn2search10

- `email` (string)
- `phone_number` (E.164 string; e.g. `+15551234567`)
- `external_id` (string/ID from your system)
- `first_name`, `last_name`
- `organization`, `title`
- `location` → nested object:
  - `address1`, `address2`, `city`, `region`, `country`, `zip`
- `properties` → free‑form object of custom properties

### Field clearing rules

- **Create Profile** is normally used for **new** profiles. To clear fields on existing profiles, use **Create or Update Profile** (`POST /api/profile-import`) with fields set to `null`. citeturn2search27turn0search1

## Rate Limits

From the official reference: citeturn0search12

- **Burst:** `75/s`
- **Steady:** `700/m`
- **Max payload size:** `100KB` per request.

## Ordering & Dependencies

- Use this endpoint primarily for **pure creates**. For general UPSERT behavior, prefer `POST /api/profile-import` (Create or Update Profile). citeturn2search27
- For **large backfills** (thousands of profiles), use **Bulk Import Profiles** (`POST /api/profile-bulk-import-jobs`) instead; that endpoint is job‑based and asynchronous. citeturn2search3turn2search5

## Failure Modes & Gotchas

- If the payload exceeds 100KB or is invalid JSON, the API returns **4xx** with details.
- If you attempt to create a logically duplicate profile (same `email` etc.), Klaviyo still allows it unless constrained by your own logic; de‑duplication is handled at the data‑model and UI layer, not by this endpoint alone. citeturn0search23
- Missing identifiers are allowed, but strongly discouraged in production pipelines since downstream events/objects will not be able to reference the profile reliably.