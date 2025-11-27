# Events API – Create Event and Get Events

## Create Event (`POST /api/events`)

- **Method:** `POST`
- **URL:** `https://a.klaviyo.com/api/events`
- **Purpose:** Create a new event to track a profile’s activity.
- **Synchronous / Asynchronous:** Synchronous ingestion.

### Required Headers

- `Authorization: Klaviyo-API-Key {YOUR_PRIVATE_API_KEY}`
- `Accept: application/vnd.api+json`
- `Content-Type: application/vnd.api+json`
- `revision: {SUPPORTED_REVISION}`

### Request Body Structure

```json
{
  "data": {
    "type": "event",
    "attributes": {
      "properties": {
        "order_id": "ORDER-123",
        "value": 99.95
      },
      "time": "2025-01-15T10:30:00Z"
    },
    "relationships": {
      "metric": {
        "data": {
          "type": "metric",
          "attributes": {
            "name": "Placed Order"
          }
        }
      },
      "profile": {
        "data": {
          "type": "profile",
          "attributes": {
            "email": "customer@example.com"
          }
        }
      }
    }
  }
}
```

Documentation highlights:

- Metric object must include at least the metric `name`.
- Profile object must include at least one identifier (e.g., `id`, `email`, or `phone_number`).
- The endpoint can create a new profile or update an existing profile’s properties based on the profile relationship.

### Identifiers

- At minimum:
  - A metric `name`.
  - One profile identifier such as `id`, `email`, or `phone_number`.

**Not specified in Klaviyo documentation:** Priority order when multiple profile identifiers are present in the same request.

### Response

- Returns an `event` resource in `data` with relationships to the associated metric and profile.

### Rate Limits and Scopes

- Required scope:
  - `events:write`
- Rate limits for this endpoint are documented in the reference and typically align with higher-volume limits for event ingestion.

---

## Get Events (`GET /api/events`)

- **Method:** `GET`
- **URL:** `https://a.klaviyo.com/api/events`
- **Purpose:** Retrieve events from the account.
- **Synchronous / Asynchronous:** Synchronous read.

### Required Headers

- `Authorization: Klaviyo-API-Key {YOUR_PRIVATE_API_KEY}`
- `Accept: application/vnd.api+json`
- `revision: {SUPPORTED_REVISION}`

### Behavior

- Returns a paginated list of events.
- Maximum **200 events per page**.
- Supported sort fields:
  - `datetime`
  - `timestamp`
- Custom metrics are not supported in the `metric_id` filter.

### Query Parameters

- `sort=FIELD` or `sort=-FIELD` where field is `datetime` or `timestamp`.
- `filter` supporting event filtering, subject to documented constraints (e.g., no custom metrics in `metric_id`).
- `page[size]`, `page[cursor]` for pagination.
- `include` to include related resources such as `metric` and `profile` when supported.

### Rate Limits and Scopes

- Rate limits (documented for this endpoint):
  - Burst: `350/s`
  - Steady: `3500/m`
- Required scope:
  - `events:read`

### Error Handling

- Invalid filters or sort fields return validation errors.
- Rate limit violations return HTTP `429`.
