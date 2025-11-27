## JSON:API Resource Model

Modern resources follow the JSON:API structure:

- Top-level object:
  - `data`: a single resource object or an array of resource objects.
  - `links`: pagination links (`self`, `next`, `prev`) for list endpoints.
  - `errors`: array of error objects when a request fails.
- Resource object fields:
  - `type`: the resource type string (e.g., `profile`, `event`, `catalog-item`, `catalog-category`).
  - `id`: resource identifier string.
  - `attributes`: resource-specific fields.
  - `relationships`: related resources.

## Relationships Object

- Relationships between resources are represented under `relationships`.
- Each relationship usually has:
  - `data`: resource identifier(s) (`type` + `id`).
  - `links`: URLs for accessing the related resources (`self`, `related`).

Examples of common relationships:

- Events:
  - `metric`: identifies the metric associated with the event.
  - `profile`: identifies the profile that performed the event.
- Profiles:
  - Relationships to lists, subscriptions, or other resources may be exposed via `relationships` and can be included or queried with `include` and `filter` parameters.
- Catalog resources:
  - Items, variants, and categories are linked via relationships (`categories` on an item, `items` on a category, `variants` on an item, etc.).

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

