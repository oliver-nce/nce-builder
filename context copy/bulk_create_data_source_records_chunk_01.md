# Bulk Create Data Source Records — `POST /api/data-source-record-bulk-create-jobs`

## Summary

Create an **asynchronous bulk job** to ingest up to 500 raw records into a specific data source. These records are later mapped into **custom object records** via Object Manager. citeturn1search1turn2search6turn0search3

## Endpoint

- **URL:** `https://a.klaviyo.com/api/data-source-record-bulk-create-jobs`
- **Method:** `POST`
- **Execution model:**  
  - Request itself is **synchronous** and returns a `data-source-record-bulk-create-job` resource.  
  - Record ingestion is **asynchronous**; records are processed after the job runs. citeturn2search6turn2search4

## Authentication & Scopes

- **Auth:** Private key or OAuth token.
- **Scopes:** `custom-objects:write`. citeturn2search6turn2search0

## Request Schema (high‑level)

- `data.type` = `"data-source-record-bulk-create-job"`
- `data.attributes`:
  - `data-source-records.data` — array of `"data-source-record"` resources: citeturn1search4turn2search6
    - Each record has:
      - `type` = `"data-source-record"`
      - `attributes.record` — free‑form JSON payload representing your source row (e.g., reservation, registration, subscription).

Example snippet from docs: citeturn1search4turn2search6


```json
{
  "data": {
    "type": "data-source-record-bulk-create-job",
    "attributes": {
      "data-source-records": {
        "data": [
          {
            "type": "data-source-record",
            "attributes": {
              "record": {
                "reservation_id": "4d5j4dH",
                "created_at": "2019-07-29T09:18:52.005234+14:00",
                "guest_count": 6,
                "late_cancellation_fee": 32.40,
                "is_active": true,
                "email": "[email protected]"
              }
            }
          }
        ]
      }
    }
  }
}
```


## Rate Limits & Batch Limits

From the reference: citeturn2search6

- **Max records per request:** `500`
- **Max payload size:** `4MB` total, `512KB` per record
- **Rate limits:**
  - **Burst:** `3/s`
  - **Steady:** `15/m`

## Ordering & Dependencies

- Requires an existing **data source** (`POST /api/data-sources`) and its `id`. citeturn2search4turn2search0
- After ingestion:
  - Object Manager maps `record` fields into **custom objects** and links them to profiles as configured. citeturn0search3turn0search11turn0search18
- Record ingestion is asynchronous; use job‑monitoring endpoints (revision‑specific) to track completion before relying on objects in flows/segments/templates.

## Failure Modes & Gotchas

- Exceeding batch or size limits yields **4xx** errors at job creation time.
- Schema changes (adding new properties in `record`) require updating the **object mapping** in the UI to expose them on the custom object; otherwise they remain unmapped and invisible in templates/segments. citeturn0search11turn0search24