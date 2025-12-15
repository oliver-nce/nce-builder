# NCE Klaviyo Integration - Project Handoff Documentation

**Last Updated:** December 12, 2025  
**Version:** 4.0.0  
**Environment:** WordPress Plugin on WP Engine (ncesoccerdev1.wpenginepowered.com)

---

## Table of Contents
1. [Project Overview](#project-overview)
2. [Available Tasks](#available-tasks)
3. [File Structure](#file-structure)
4. [Database Schema](#database-schema)
5. [Logging & Debugging](#logging--debugging)
6. [Klaviyo API Details](#klaviyo-api-details)
7. [Common Issues & Solutions](#common-issues--solutions)
8. [Deployment & Operations](#deployment--operations)
9. [CLI Commands Reference](#cli-commands-reference)

---

## Project Overview

This WordPress plugin syncs customer data from a custom database to Klaviyo using multiple specialized tasks. The system supports profile syncing, consent management, and data source uploads.

### Key Features
- **Task-based architecture:** Modular tasks for different operations
- **Full Sync orchestration:** Task 9 runs all sync operations in sequence
- **Admin Widget:** HTML widget for easy one-click sync from WordPress admin
- **Batch processing:** Configurable batch sizes with rate limit handling
- **Comprehensive logging:** Real-time progress in database and log files
- **REST API triggers:** Simple HTTP endpoints to start any task

### Performance Summary

| Metric | Value |
|--------|-------|
| **Profiles Dataset** | ~17,000 records |
| **Optimal Batch Size** | 450 records |
| **Full Sync Time** | 3-5 minutes |
| **Default Lookback** | 14 hours |
| **Execution Mode** | Synchronous |

---

## Available Tasks

| Task | Name | Purpose | Documentation |
|------|------|---------|---------------|
| **1** | Upload Data (Family Members) | Sync family member data to Klaviyo data source | [Task 1](wp-content/wp-custom-scripts/nce-runner-task-1/) |
| **2** | Delete Data Sources | Remove all Klaviyo data sources | [Task 2](wp-content/wp-custom-scripts/nce-runner-task-2/) |
| **3** | Bulk Upsert Profiles | Create/update profile attributes in Klaviyo | [Task 3](wp-content/wp-custom-scripts/nce-runner-task-3/) |
| **4** | Grant Email Consent | Grant email marketing consent (NEW profiles) | [Task 4](wp-content/wp-custom-scripts/nce-runner-task-4/) |
| **5** | Grant SMS Consent | Grant SMS marketing consent (NEW profiles) | [Task 5](wp-content/wp-custom-scripts/nce-runner-task-5/) |
| **6** | Bulk Unsubscribe | Unsubscribe emails from marketing | [Task 6](wp-content/wp-custom-scripts/nce-runner-task-6/) |
| **7** | Fetch & Cache Profiles | Cache profiles with phone numbers to database | [Task 7](wp-content/wp-custom-scripts/nce-runner-task-7/) |
| **8** | Process Cached Profiles | Grant SMS consent from cached profiles | [Task 8](wp-content/wp-custom-scripts/nce-runner-task-8/) |
| **9** | Full Sync | Orchestrate Tasks 3 → 1 → 4 → 5 → 1b | [Task 9](wp-content/wp-custom-scripts/nce-runner-task-9/) |

### Task 9: Full Sync (Primary Workflow)

Task 9 is the main orchestration task that runs multiple sub-tasks in sequence:

1. **Task 3:** Bulk upsert profiles from database
2. **Task 1:** Upload family member data to Klaviyo
3. **Task 4:** Grant email consent for new profiles
4. **Task 5:** Grant SMS consent for new profiles
5. **Task 1b:** Upload enrollment data to Klaviyo

**URL:**
```
https://your-site.com/wp-json/nce/v1/run?task=9
https://your-site.com/wp-json/nce/v1/run?task=9&lookback_hours=24
https://your-site.com/wp-json/nce/v1/run?task=9&skip_tasks=1,5
```

**Parameters:**
- `lookback_hours` (default: 14): How far back to look for new profiles
- `skip_tasks` (optional): Comma-separated task numbers to skip (e.g., "1,5")
- `job_name` (default: "default"): Job identifier for logging

**Admin Widget:**
A ready-to-use HTML widget is available at `wp-content/wp-custom-scripts/nce-runner-task-9/widget.html`. Embed this in any WordPress admin page using a Custom HTML block.

---

## File Structure

### Core Files

```
wp-content/
├── mu-plugins/
│   ├── klaviyo-sync-widget.php      # Widget mu-plugin
│   └── table-editor-api.php         # Table editor API
├── plugins/
│   └── nce-runner/
│       └── nce-runner.php           # Main REST endpoint plugin
└── wp-custom-scripts/
    ├── nce-runner_task_manager.php  # Central task dispatcher
    ├── temp_log.log                 # Execution logs
    ├── includes/                    # Shared utilities
    ├── nce-runner-task-1/           # Upload family members + enrollment
    │   ├── klaviyo_write_objects.php
    │   └── klaviyo_write_objects_optimized.php
    ├── nce-runner-task-2/           # Delete data sources
    │   └── delete_all_data_sources.php
    ├── nce-runner-task-3/           # Bulk upsert profiles
    │   └── bulk_upsert_profiles.php
    ├── nce-runner-task-4/           # Email consent
    │   ├── grant_email_consent.php
    │   └── README.md
    ├── nce-runner-task-5/           # SMS consent
    │   └── grant_sms_consent.php
    ├── nce-runner-task-6/           # Bulk unsubscribe
    │   └── bulk_unsubscribe_emails.php
    ├── nce-runner-task-7/           # Fetch & cache profiles
    │   ├── backfill_sms_consent.php
    │   ├── fetch_and_cache_profiles.php
    │   └── README.md
    ├── nce-runner-task-8/           # Process cached profiles
    │   └── process_cached_profiles.php
    └── nce-runner-task-9/           # Full sync orchestrator
        ├── run_full_sync.php
        └── widget.html
```

### Task Manager (`nce-runner_task_manager.php`)

The central dispatcher that routes requests to task handlers. Maps task numbers to their file paths and functions.

---

## Database Schema

### Table: `wp_klaviyo_globals`

Stores Klaviyo API configuration and job state.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `api_key` | VARCHAR(255) | Klaviyo Private API Key |
| `api_version` | VARCHAR(255) | API revision (e.g., "2025-04-15") |
| `object_ds_id` | VARCHAR(255) | Klaviyo Data Source ID |
| `object_query` | TEXT | Primary SQL query |
| `query_to_use` | SMALLINT | Which query to execute (1, 2, or 3) |
| `batch_size` | SMALLINT | Records per batch (default: 450) |
| `batch_limit` | SMALLINT | Max batches per run (0 = unlimited) |
| `starting_offset` | INT | Starting offset for batch processing |
| `last_result` | LONGTEXT | Running log of batch results |

---

## Logging & Debugging

### Log File: `temp_log.log`

Location: `/wp-content/wp-custom-scripts/temp_log.log`

**Format (Full Sync):**
```
[2025-12-12 14:30:00] ========== FULL SYNC STARTED ==========
[14:30:00] Job: default
[14:30:00] Profiles job: profiles
[14:30:00] Lookback hours: 14

[14:30:01] ▶️  STARTING Task 3: Bulk Upsert Profiles
[14:30:45] ✅ Task 3 completed in 44.2s

[14:30:46] ▶️  STARTING Task 1: Upload Data to Klaviyo
[14:31:30] ✅ Task 1 completed in 44.5s

[14:31:31] ▶️  STARTING Task 4: Grant Email Consent
[14:31:45] ✅ Task 4 completed in 14.2s

[14:31:46] ▶️  STARTING Task 5: Grant SMS Consent
[14:32:00] ✅ Task 5 completed in 14.1s

[14:32:01] ▶️  STARTING Task 1b: Upload Enrollment Data to Klaviyo
[14:32:45] ✅ Task 1b completed in 44.3s

[14:32:45] ========== FULL SYNC COMPLETE ==========
[14:32:45] Total duration: 165.2s
[14:32:45] Status: SUCCESS
```

### Watch Live
```bash
ssh ncesoccerdev1.wpenginepowered.com "tail -f /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log"
```

---

## Klaviyo API Details

### Authentication
- **Method:** API Key in `Authorization` header
- **Format:** `Authorization: Klaviyo-API-Key pk_abc123...`
- **Versioning:** `revision` header (e.g., `revision: 2025-04-15`)

### Key Endpoints

| Endpoint | Purpose |
|----------|---------|
| `POST /api/profile-bulk-import-jobs` | Bulk upsert profiles |
| `POST /api/profile-subscription-bulk-create-jobs` | Bulk subscribe profiles |
| `POST /api/data-source-record-bulk-create-jobs` | Upload data source records |

---

## Common Issues & Solutions

### Issue: Task 9 Takes Too Long
**Solution:** Reduce lookback_hours or use skip_tasks to skip unnecessary tasks:
```bash
curl "https://your-site.com/wp-json/nce/v1/run?task=9&skip_tasks=1"
```

### Issue: 502 Bad Gateway
**Cause:** Script timeout
**Solution:** 
- Reduce `lookback_hours`
- Run individual tasks instead of full sync
- Check server timeout settings

### Issue: Old Code Running
**Solution:** Check line 2 timestamp on server vs local file:
```bash
ssh your-server "head -2 /path/to/file.php"
```

### Issue: Widget Not Working
**Solution:** Ensure the REST endpoint is accessible:
```bash
curl "https://your-site.com/wp-json/nce/v1/run?task=9"
```

---

## Deployment & Operations

### Quick Start

**Run Full Sync via REST API:**
```bash
curl "https://ncesoccerdev1.wpenginepowered.com/wp-json/nce/v1/run?task=9"
```

**Run with Custom Lookback:**
```bash
curl "https://ncesoccerdev1.wpenginepowered.com/wp-json/nce/v1/run?task=9&lookback_hours=24"
```

**Skip Specific Tasks:**
```bash
curl "https://ncesoccerdev1.wpenginepowered.com/wp-json/nce/v1/run?task=9&skip_tasks=1,5"
```

### Using the Admin Widget

1. Navigate to a WordPress admin page
2. Add a Custom HTML block
3. Paste contents of `wp-content/wp-custom-scripts/nce-runner-task-9/widget.html`
4. Click "Update Klaviyo Profiles, Family Members and Enrollments"

---

## CLI Commands Reference

### Run Tasks
```bash
# Full sync
curl "https://your-site.com/wp-json/nce/v1/run?task=9"

# Individual tasks
curl "https://your-site.com/wp-json/nce/v1/run?task=3"
curl "https://your-site.com/wp-json/nce/v1/run?task=4&lookback_hours=24"
```

### Monitor Logs
```bash
# Watch live
ssh ncesoccerdev1.wpenginepowered.com "tail -f /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log"

# Last 50 lines
ssh ncesoccerdev1.wpenginepowered.com "tail -50 /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log"
```

### Database Queries
```bash
# Get full config
wp option get klaviyo_globals --format=json

# Update batch size
wp db query "UPDATE wp_klaviyo_globals SET batch_size = 450 WHERE id = 1"
```

---

## Support & Troubleshooting

### Diagnostic Checklist

- [ ] Plugin is active (`wp plugin list`)
- [ ] `api_key` is set in `wp_klaviyo_globals`
- [ ] REST endpoint returns valid response
- [ ] No PHP fatal errors in logs
- [ ] Timestamp on server matches local file

### Key Contacts & Resources

**Klaviyo Documentation:**
- [Profiles API](https://developers.klaviyo.com/en/reference/profiles_api_overview)
- [Data Sources API](https://developers.klaviyo.com/en/reference/data-sources)
- [Rate Limits](https://developers.klaviyo.com/en/docs/rate_limits)

**Server Access:**
- Host: ncesoccerdev1.wpenginepowered.com
- Method: SSH with certificate authentication

---

*End of Project Handoff Documentation*
