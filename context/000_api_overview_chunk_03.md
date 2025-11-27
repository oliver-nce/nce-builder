## Pagination, Filtering, Sorting, and Sparse Fieldsets

Modern endpoints use standard JSON:API-style query parameters:

- `page[size]`, `page[cursor]`:
  - Cursor-based pagination controls.
- `filter`:
  - Used to restrict resources based on field values (syntax documented in specific guides and references).
- `sort`:
  - Optional ordering of list results; supported fields are documented per endpoint.
- `fields[resource-type]`:
  - Sparse fieldsets allowing the client to request only specific attributes or related resources for a given type.
- `include`:
  - Used to include related resources in the same response (e.g., include catalog variants when fetching catalog items).

The exact fields and operators supported for `filter`, `sort`, `fields`, and `include` are endpoint-specific and documented in the corresponding reference entries.

## Rate Limiting and Error Handling (Summary)

- All modern endpoints are rate-limited per account using a fixed-window algorithm with two windows:
  - **Burst window**: 1-second window.
  - **Steady window**: 1-minute window.
- Unless otherwise specified on the endpoint, each endpoint is assigned one of the predefined rate limit classes:
  - XS: 1/s burst; 15/m steady
  - S: 3/s burst; 60/m steady
  - M: 10/s burst; 150/m steady
  - L: 75/s burst; 700/m steady
  - XL: 350/s burst; 3500/m steady
- Actual limits for a given endpoint are listed in its API reference entry.
- When limits are exceeded, the API returns HTTP `429 Too Many Requests`.
- OAuth apps have separate rate limiting rules; this corpus focuses on private-key integrations.

Error objects:

- Errors are returned within an `errors` array at the top level.
- Each error includes:
  - `status`: HTTP status code as a string.
  - `title`: short summary of the error category.
  - `detail`: human-readable explanation.
  - `source` and/or `meta`: additional details about the field(s) that caused the error.
- Bulk job endpoints report resource-level errors inside the job status resource (see the catalogs and bulk import sections).

## Scopes

- Each endpoint declares the scopes required for access (e.g., `profiles:read`, `profiles:write`, `events:read`, `catalogs:read`).
- The API key must be configured with scopes that jointly satisfy all required scopes for the endpoints used.
- If scopes are insufficient, the API responds with an authorization error.

## Contract Notes

- Behavior, fields, and rate limits documented in this corpus are taken directly from the published Klaviyo API documentation for the selected revision(s).
- If a behavior is not explicitly described in the documentation, it is annotated as:
  - **Not specified in Klaviyo documentation.**
- PHP client code should treat this corpus as the contract for all automated integrations.
