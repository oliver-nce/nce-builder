# Create Data Source — `POST /api/data-sources`

## Summary

Create a **Custom Objects data source**, which acts as the origin container for records that will later be mapped into custom objects (via Object Manager). Returns a unique data source ID. citeturn2search0turn2search4turn0search3

## Endpoint

- **URL:** `https://a.klaviyo.com/api/data-sources`
- **Method:** `POST`
- **Execution model:** **Synchronous** — the data source is created inline and returned in the response. citeturn2search0turn2search4

## Authentication & Scopes

- **Auth:** Private key or OAuth token.
- **Scopes:** `custom-objects:write`. citeturn2search0turn2search6

## Request Schema (high‑level)

- `data.type` = `"data-source"`
- `data.attributes`:
  - `visibility` — `"private"` or `"public"` (typical examples use `"private"`). citeturn2search4
  - `title` — human‑readable name (e.g. `"Reservation Database"`)
  - `description` — optional free‑form description

Example (from docs): citeturn2search4


```json
{
  "data": {
    "type": "data-source",
    "attributes": {
      "visibility": "private",
      "title": "Reservation Database",
      "description": "The source of truth for reservations"
    }
  }
}
```


## Rate Limits

From the reference: citeturn2search0

- **Burst:** `3/s`
- **Steady:** `60/m`

## Ordering & Dependencies

- This call usually runs **once per logical dataset** (e.g., Reservations, Registrations, Sessions).
- After creation:
  - Use the returned data source `id` when calling **Bulk Create Data Source Records** (`POST /api/data-source-record-bulk-create-jobs`). citeturn2search6turn0search3
  - Define the actual **custom object** in the **Object Manager** UI, mapping fields from `record` payloads to object properties. citeturn0search3turn0search11turn0search18

## Failure Modes & Gotchas

- Data source creation is lightweight; errors typically occur only if:
  - The payload is malformed JSON.
  - Required fields (`visibility`, `title`) are missing.
  - You lack the `custom-objects:write` scope.
- Once objects are mapped and in use, deleting a data source or changing mappings can have cascading effects on flows/segments/templates. citeturn0search11turn0search24