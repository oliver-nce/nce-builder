# Global Rate Limits, Status Codes, and Errors

## Rate Limits

Klaviyo applies per-account rate limits for each endpoint using a fixed-window algorithm with:

- A 1-second burst window.
- A 1-minute steady window.

Unless otherwise documented, each endpoint uses one of the following rate limit classes:

- XS: 1/s burst; 15/m steady.
- S: 3/s burst; 60/m steady.
- M: 10/s burst; 150/m steady.
- L: 75/s burst; 700/m steady.
- XL: 350/s burst; 3500/m steady.

Endpoint reference pages give the exact burst and steady limits for each endpoint and revision.

### OAuth vs Private Key

- OAuth apps receive their own rate limit quota per installed app instance (per account per app).
- Private key integrations share the same quota for the account.
- This corpus focuses on private-key usage.

## Behavior When Limits Are Exceeded

- When rate limits are exceeded, the API returns HTTP `429 Too Many Requests`.
- Clients should implement retry logic, ideally with exponential backoff, when `429` or transient `5xx` status codes are returned.

## Status Codes and Error Objects

- Errors are provided in a top-level `errors` array following JSON:API conventions.
- Each error typically includes:
  - `status`: HTTP status code string.
  - `title`: short category label.
  - `detail`: human-readable description.
  - `source` and/or `meta`: where the error occurred or which field caused it.

Common patterns include:

- `400 Bad Request`: invalid input or malformed request.
- `401 Unauthorized`: missing or invalid authentication.
- `403 Forbidden`: missing required scopes.
- `404 Not Found`: resource not found or inaccessible.
- `409 Conflict`: conflicting values such as duplicate identifiers.
- `422 Unprocessable Entity`: semantically invalid request (e.g., failing validation).
- `429 Too Many Requests`: rate limit exceeded.
- `5xx`: server-side errors or temporary issues.

**Not specified in Klaviyo documentation:** A complete, immutable list of error `title` and `detail` values; these may evolve with the platform.

## Bulk Job Error Reporting

- Bulk endpoints for profiles and catalogs create jobs whose status is checked through dedicated job-status endpoints.
- Detailed per-resource errors for bulk operations are returned in job status responses, typically inside an `errors` array with per-resource metadata.
- Initial bulk job creation responses do not contain per-resource error details; they only confirm job creation and provide a job ID.
