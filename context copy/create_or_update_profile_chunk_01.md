# Create or Update Profile — `POST /api/profile-import`

## Summary

Perform a **profile UPSERT**: create a new profile or update an existing one, based on supplied identifiers. Returns `201` for create and `200` for update. citeturn2search27

## Endpoint

- **URL:** `https://a.klaviyo.com/api/profile-import`
- **Method:** `POST`
- **Execution model:** **Synchronous request** — profile is validated and written inline; response status indicates create vs update. citeturn2search27

## Authentication & Scopes

- **Auth:** Server‑side; **private API key** or OAuth access token. citeturn0search7  
- **Required scopes:** `profiles:write`. citeturn2search27

## Identifiers & Match Rules

At least **one identifier** must be provided in `data.attributes`: citeturn2search10turn2search27

- `email`
- `phone_number`
- `external_id`
- or existing profile `id`

Klaviyo uses these identifiers to **find an existing profile**; if none matches, a **new profile** is created.

## Request Schema (high‑level)

### Required

- `data.type` = `"profile"`
- `data.attributes` object containing at least one identifier (`email`, `phone_number`, `external_id`, or `id`). citeturn2search27turn2search10

### Common optional attributes

Same structure as Create Profile, e.g.: citeturn2search10

- `first_name`, `last_name`
- `organization`, `title`
- `location.{address1,address2,city,region,country,zip}`
- `properties` for custom fields
- `subscriptions` / predictive analytics via `additional-fields` response parameter

### Field update semantics

- Fields **included** in `attributes` are **updated/overwritten**.
- Fields **omitted** are **left unchanged**.
- Fields set explicitly to `null` are **cleared** on the profile. citeturn2search27turn0search1

## Rate Limits

From the Create or Update Profile reference: citeturn0search4turn2search27

- **Burst:** `75/s`
- **Steady:** `700/m`

(Implementation note: behaves consistently with `POST /api/profiles` in practice.)

## Ordering & Dependencies

- Use this endpoint for **transactional UPSERTs** (e.g., signup, checkout, lead‑capture) where you need immediate profile updates.
- Safe to call **immediately before or after** event creation (`POST /api/events`); identifiers are available as soon as the response returns.
- For bulk updates (thousands of profiles), use **Bulk Import Profiles** instead. citeturn2search5turn2search3

## Failure Modes & Gotchas

- If no valid identifier is provided, the call fails with **4xx**.
- Large payloads or nested objects beyond allowed size (`100KB`) will be rejected. citeturn0search4turn0search12
- When migrating from legacy Track/Identify APIs, remember that `POST /api/profile-import` is **server‑side only**; client‑side should use **Create Client Profile** with a public key. citeturn0search15turn2search25