# Catalogs API – Core Concepts and Get Catalog Items

## Catalogs Data Model Summary

Klaviyo’s Catalogs API defines the following resource types:

- `catalog-item`
- `catalog-variant`
- `catalog-category`
- Back-in-stock subscriptions

### Shared Attributes for Items and Variants

- `id`: resource ID within Klaviyo.
- `attributes.external_id` (required):
  - ID of the item or variant in an external system.
- `attributes.integration_type`:
  - Integration type (e.g., `$custom`).
  - Currently only custom integrations are supported for these endpoints.
- `attributes.title` (required):
  - Title of the item or variant.
- `attributes.description` (required):
  - Description of the item or variant.
- `attributes.url` (required):
  - URL of the item or variant on the website.
- `attributes.price`:
  - Price used in emails and flows.
- `attributes.catalog_type`:
  - Catalog type (e.g., `$default`).
  - The modern custom catalog implementation only supports `$default` catalog type.
- Additional attributes may include images, inventory-related fields, and other custom properties.

### Bulk Operations Summary

- Bulk operations exist for items, variants, and categories:
  - Bulk create, bulk update, bulk delete.
- Each bulk operation:
  - Accepts up to **100 resources per request**.
  - Creates a job resource (e.g., `catalog-item-bulk-create-job`).
  - Requires polling job-specific endpoints for status and errors.

## Get Catalog Items (`GET /api/catalog-items`)

- **Method:** `GET`
- **URL:** `https://a.klaviyo.com/api/catalog-items`
- **Purpose:** Retrieve catalog items for the account.
- **Synchronous / Asynchronous:** Synchronous read.

### Required Headers

- `Authorization: Klaviyo-API-Key {YOUR_PRIVATE_API_KEY}`
- `Accept: application/vnd.api+json`
- `revision: {SUPPORTED_REVISION}`

### Behavior

- Returns a list of `catalog-item` resources.
- Maximum **100 items per request**.
- Supported sort field:
  - `created` (ascending or descending).
- Only the following are currently supported:
  - `integration_type` = `$custom`
  - `catalog_type` = `$default`

### Query Parameters

- `page[size]`, `page[cursor]`:
  - For pagination; follow `links.next` to traverse the result set.
- `sort`:
  - Example: `sort=created` or `sort=-created`.
- `filter`:
  - Example use cases include filtering by `category.id`.
  - Full filter syntax is described in global filter parameter documentation.
- `fields[catalog-item]`:
  - Use sparse fieldsets to request a subset of attributes.
- `include`:
  - Example: `include=variants` to include variants related to catalog items.

### Response

- `data`: array of `catalog-item` resources.
- `links`: pagination links (`self`, `next`, `prev`).
- `included`: related resources when `include` is used.

### Rate Limits and Scopes

- Rate limits:
  - Burst: `350/s`
  - Steady: `3500/m`
- Required scope:
  - `catalogs:read`

### Error Handling

- Invalid filters or sort values return validation errors.
- Rate limit violations return HTTP `429`.
