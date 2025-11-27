# Klaviyo API Overview (JSON:API Contract)

## Purpose

This document summarizes the key contractual rules from Klaviyo’s modern JSON:API-based endpoints, as described in the official developer documentation. It is intended as a stable reference for building PHP integrations that call these APIs correctly.

## Base URL

- All modern endpoints use the same base:
  - `https://a.klaviyo.com`

Paths shown in other documents are appended to this base.

## Authentication

- Authentication is performed with a **private API key**.
- Requests use an HTTP header of the form:
  - `Authorization: Klaviyo-API-Key {YOUR_PRIVATE_API_KEY}`
- The private key is associated with a single Klaviyo account; all rate limits are enforced per account for private-key integrations.

## Media Type and Revision Header

- Modern endpoints follow the JSON:API media type:
  - `Accept: application/vnd.api+json`
  - `Content-Type: application/vnd.api+json` for requests with a body.
- A **revision header** is required for modern endpoints:
  - `revision: YYYY-MM-DD`
- The revision value selects a specific version of the API contract. Newer revisions may introduce new fields or behavior. Older revisions are kept stable for backward compatibility until explicitly sunset by Klaviyo.
- The current set of supported revisions is listed in the API Reference navigation (e.g., `v2025-10-15`, `v2025-07-15`, etc.). Choose one and use it consistently across endpoints for a given integration.

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

