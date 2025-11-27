# Profiles API – Create or Update Profile (`POST /api/profile-import`)

## Endpoint

- **Method:** `POST`
- **URL:** `https://a.klaviyo.com/api/profile-import`
- **Purpose:** Create or update a profile given a set of profile attributes and optionally a profile ID.
- **Synchronous / Asynchronous:** Synchronous HTTP request; the response reflects the outcome of the single profile import.
- **Success Codes:**
  - `201 Created` – a new profile was created.
  - `200 OK` – an existing profile was updated.

## Required Headers

- `Authorization: Klaviyo-API-Key {YOUR_PRIVATE_API_KEY}`
- `Accept: application/vnd.api+json`
- `Content-Type: application/vnd.api+json`
- `revision: {SUPPORTED_REVISION}` (for example, `2025-10-15`)

## Request Body Structure

```json
{
  "data": {
    "type": "profile",
    "id": "optional-profile-id",
    "attributes": {
      "email": "person@example.com",
      "phone_number": "+15551234567",
      "external_id": "external-123",
      "first_name": "Jane",
      "last_name": "Doe",
      "properties": {
        "favorite_color": "blue",
        "vip": true
      }
    }
  }
}
```

- `data.type` must be `profile`.
- The `attributes` object follows the profile schema documented in the Profiles API overview.

### Identifier Behavior

Documentation describes the following identifiers for profiles:

- `id` (Klaviyo profile ID)
- `email`
- `phone_number`
- `external_id`

The Create or Update Profile endpoint uses these identifiers to match existing profiles or create new ones. At least one identifier is required in practice; examples in the documentation include `email`, `phone_number`, and `external_id`.

- `null` values in `attributes` clear the corresponding fields.
- Omitting a field leaves the existing value unchanged.

**Not specified in Klaviyo documentation:** Exact precedence rules when multiple identifiers are present in a single request.

## Query Parameters

- `additional-fields[profile]` may be used to include extra fields such as subscriptions or predictive analytics in the response.
- Other JSON:API parameters (`include`, `fields`) may be supported but are not the primary focus of this endpoint.

## Response

- Returns a single profile resource in `data`.
- `data.id` is the Klaviyo profile ID.
- `data.attributes` includes the profile fields after creation or update.

## Rate Limits and Scopes

- Required scopes:
  - `profiles:write`
- Rate limit values (burst/steady) are documented in the endpoint reference and typically correspond to the L rate limit class for profile reads/writes for current revisions.

## Error Handling

- Schema or validation errors return a JSON:API `errors` array.
- Missing or invalid identifiers result in appropriate error responses.
- Payloads exceeding documented size limits cause error responses.
- Exceeding rate limits results in HTTP `429 Too Many Requests`.

**Not specified in Klaviyo documentation:** Complete enumeration of all HTTP status codes that may be returned beyond standard error types.
