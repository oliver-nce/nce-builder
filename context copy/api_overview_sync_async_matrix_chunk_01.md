# Klaviyo Core API — Sync vs Async Matrix (Profiles, Events, Custom Objects)

This document summarizes execution behavior across the key Klaviyo endpoints used for profile, event, and custom‑object ingestion, to guide sequencing and error‑handling in integrations. citeturn0search19turn0search2turn0search3turn2search3turn1search5turn2search6

## Execution Model Summary

| Area           | Endpoint                                             | Method | Sync/Async Model                                                                                 |
|----------------|------------------------------------------------------|--------|--------------------------------------------------------------------------------------------------|
| Profiles       | `/api/profiles`                                     | POST   | **Synchronous create** (new profile only). citeturn0search12turn2search10                    |
| Profiles       | `/api/profile-import`                               | POST   | **Synchronous UPSERT** (create or update). citeturn2search27turn2search10                   |
| Bulk Profiles  | `/api/profile-bulk-import-jobs`                     | POST   | **Job created synchronously; profile processing asynchronous.** citeturn2search3turn0search1 |
| Bulk Profiles  | `/api/profile-bulk-import-jobs`                     | GET    | **Synchronous** job listing. citeturn2search9                                               |
| Bulk Profiles  | `/api/profile-bulk-import-jobs/{id}`                | GET    | **Synchronous** single job status. citeturn2search11                                        |
| Bulk Profiles  | `/api/profile-bulk-import-jobs/{id}/import-errors`  | GET    | **Synchronous** read of stored errors. citeturn2search2turn2search29                       |
| Bulk Profiles  | `/api/profile-bulk-import-jobs/{id}/profiles`       | GET    | **Synchronous** read of associated profiles. citeturn2search14turn2search19                |
| Events         | `/api/events`                                       | POST   | Request is **sync**; event processing is **async** (accepted & queued). citeturn1search12turn1search2 |
| Bulk Events    | `/api/event-bulk-create-jobs`                       | POST   | **Job created synchronously; events processed asynchronously.** citeturn1search3turn1search5 |
| Data Sources   | `/api/data-sources`                                 | POST   | **Synchronous** data source create. citeturn2search0turn2search4                           |
| Data Source Recs | `/api/data-source-record-bulk-create-jobs`        | POST   | **Job created synchronously; records processed asynchronously.** citeturn2search6turn1search4 |

## Practical Integration Rules

1. **Transactional flows (signup/checkout)**  
   - Use `POST /api/profile-import` for profile UPSERT.  
   - Immediately follow with `POST /api/events` for transactional events.  
   - Treat events as **eventually consistent** in UI/flows.

2. **Bulk/backfill flows**  
   - Profiles: `POST /api/profile-bulk-import-jobs` → poll job → inspect errors/results.  
   - Events: `POST /api/event-bulk-create-jobs` → poll job → inspect errors/results.  
   - Custom objects: `POST /api/data-source-record-bulk-create-jobs` → poll job → map in Object Manager.

3. **Field clearing rules**  
   - **Allowed via UPSERT (`/api/profile-import`)**: set fields to `null` to clear. citeturn2search27turn0search1  
   - **Not allowed in Bulk Import Profiles**: bulk jobs ignore `null` clears; only non‑null fields are updated. citeturn2search5

4. **Identifier strategy**  
   - Always provide a stable `external_id` plus `email` where possible. citeturn2search10turn0search23  
   - Use the same identifiers when linking events and custom objects back to profiles.

This matrix should be treated as the **authoritative behavioral summary** for Cursor agents reasoning about sequencing, retries, and dependencies across Klaviyo’s ingestion APIs.