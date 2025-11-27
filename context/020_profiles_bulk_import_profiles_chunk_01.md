# Profiles API – Bulk Import Profiles (`POST /api/profile-bulk-import-jobs`)

## Endpoint

- **Method:** `POST`
- **URL:** `https://a.klaviyo.com/api/profile-bulk-import-jobs`
- **Purpose:** Create a bulk profile import job to create or update a batch of profiles.
- **Synchronous / Asynchronous:** Asynchronous. The HTTP response confirms job creation; the job runs in the background.

## Required Headers

- `Authorization: Klaviyo-API-Key {YOUR_PRIVATE_API_KEY}`
- `Accept: application/vnd.api+json`
- `Content-Type: application/vnd.api+json`
- `revision: {SUPPORTED_REVISION}`

## Request Body Structure

```json
{
  "data": {
    "type": "profile-bulk-import-job",
    "attributes": {
      "profiles": {
        "data": [
          {
            "type": "profile",
            "attributes": {
              "email": "person1@example.com",
              "external_id": "ext-1"
            }
          },
          {
            "type": "profile",
            "attributes": {
              "email": "person2@example.com",
              "external_id": "ext-2"
            }
          }
        ]
      }
    }
  }
}
```

- `data.type` must be `profile-bulk-import-job`.
- `attributes.profiles.data` is an array of profile resources, each following the profile schema.

### Identifiers

- Each profile in the job uses the same identifiers as other profile endpoints:
  - `id` (Klaviyo profile ID)
  - `email`
  - `phone_number`
  - `external_id`
- At least one identifier per profile is required in practice to match or create profiles correctly.

**Not specified in Klaviyo documentation:** The exact precedence when multiple identifiers are present for a single profile in the same job.

## Limits

Documentation specifies the following constraints:

- Maximum profiles per request: **10,000**.
- Maximum payload size per request: **5 MB**.
- Maximum payload size per profile: **100 KB**.

Client code should enforce these limits before calling the API.

## Response

- Returns a job resource with:
  - `data.type`: `profile-bulk-import-job`.
  - `data.id`: bulk job ID.
  - Attributes describing initial job state (e.g., status and counts).

Detailed success/failure information for individual profiles is available via job status and relationship endpoints, not the creation response.

## Rate Limits and Scopes

- Rate limits (from the endpoint reference):
  - Burst: `10/s`
  - Steady: `150/m`
- Required scopes:
  - `lists:write`
  - `profiles:write`

## Error Handling

- Violations of profile count or payload size limits produce error responses.
- Invalid profile data yields validation errors in `errors`.
- Rate limit violations result in HTTP `429`.

**Not specified in Klaviyo documentation:** Retention policy or lifetime of bulk job records.
