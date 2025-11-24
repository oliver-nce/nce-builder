# NCE Klaviyo Integration - Project Handoff Documentation

**Last Updated:** November 24, 2025  
**Version:** 3.0.0  
**Environment:** WordPress Plugin on WP Engine (ncesoccerdev1.wpenginepowered.com)

---

## Table of Contents
1. [Project Overview](#project-overview)
2. [File Structure](#file-structure)
3. [Database Schema](#database-schema)
4. [Logging & Debugging](#logging--debugging)
5. [Klaviyo API Details](#klaviyo-api-details)
6. [Common Issues & Solutions](#common-issues--solutions)
7. [Deployment & Operations](#deployment--operations)
8. [CLI Commands Reference](#cli-commands-reference)

---

## Project Overview

This WordPress plugin uploads customer data from a custom database table to Klaviyo's Data Source API in batches. Since **Klaviyo processes bulk uploads asynchronously**, there's no need for WP-Cron scheduling. The plugin runs **synchronously** (Task 3), sending all batches to Klaviyo and returning the complete result. Klaviyo handles async processing on their end.

### Key Features
- **Synchronous Execution:** Runs immediately, returns complete result when all API calls finish (2-3 minutes)
- **Batch Upload:** 450 records per batch (configurable via `batch_size` field)
- **Rate Limit Handling:** Exponential backoff with dynamic delay adjustment
- **Comprehensive Logging:** Real-time progress in database and log files
- **REST API Trigger:** Simple HTTP endpoint to start uploads
- **Database Progress Tracking:** Stores status and results with real-time visibility

### Performance Summary

| Metric | Value |
|--------|-------|
| **Dataset Size** | 17,000 records |
| **Optimal Batch Size** | 450 records |
| **Time Per Batch** | ~5 seconds |
| **Total Batches** | ~38 batches |
| **Total Time** | **2-3 minutes** |
| **Execution Mode** | **Synchronous** (Klaviyo handles async processing) |

**Key Insight:** Klaviyo's API returns HTTP 204 immediately and processes records asynchronously (~15 minutes). Our plugin sends all batches synchronously in 2-3 minutes, then Klaviyo finishes processing on their end.

---

## File Structure

### Core Plugin Files

#### `/src/nce-runner.php` (~250 lines)
**Purpose:** Main WordPress plugin file  
**Functions:**
- Registers REST API endpoint: `/wp-json/nce/v1/run`
- Handles two tasks:
  - **Task 1:** Test stub response (for connectivity testing)
  - **Task 3:** Synchronous batch upload (production use)
- **Task 3 behavior:**
  - **Clears log files** (`temp_log.log` and `nce-runner-debug.log`) at start
  - **Loads required files** and calls `klaviyo_write_objects()` directly
  - **Returns complete result** after all batches sent to Klaviyo (waits for completion)
  - **Execution time limit:** 30 minutes
- Provides custom debug logger `nce_debug_log()`
- Suppresses third-party plugin errors in REST responses

**Key Hooks:**
- `rest_api_init` - Registers REST endpoint

**REST API Usage:**

**Task 3 (Upload - runs synchronously):**
```bash
curl -X POST https://ncesoccerdev1.wpenginepowered.com/wp-json/nce/v1/run \
  -H "Content-Type: application/json" \
  -d '{"task": 3}'
```

**Response (Task 3):**
```json
{
  "ok": true,
  "task": 3,
  "status": "completed",
  "duration_seconds": 125.4,
  "result": {
    "success": true,
    "message": "Job completed - see last_result field for details"
  }
}
```

---

#### `/src/klaviyo_write_objects.php` (525 lines)
**Purpose:** Core batch upload function  
**Main Function:** `klaviyo_write_objects(array $payload): array`

**Workflow:**
1. Set PHP execution time to 30 minutes, memory to 512MB
2. Enable MySQL autocommit with `SET autocommit=1` for real-time visibility
3. Read configuration from `wp_klaviyo_globals` table including:
   - API credentials (`api_key`, `api_version`)
   - Data source ID (`object_ds_id`)
   - SQL queries (`object_query`, `object_query_2`, `object_query_3`)
   - Query selector (`query_to_use`: 1=object_query, 2=object_query_2, 3=object_query_3)
   - Batch settings (`batch_size`, `batch_limit`, `starting_offset`)
   - Control parameter (`control_param_2`)
4. Validate `batch_size` is between 1-1000
5. Clear `first_batch_payload` field and set `last_result` to "Cron job has started\n" + `COMMIT`
6. Initialize offset from `starting_offset` field (default: 0)
7. Log configuration (batch size, offset, control params if > 0)
8. Loop through batches:
   - Query `batch_size` records with `LIMIT batch_size OFFSET n` (starting from `starting_offset`)
   - Format as JSON:API payload (using official Klaviyo documented format)
   - POST to Klaviyo bulk create endpoint
   - Extract `x-klaviyo-req-id` from response headers
   - **Append batch result to `last_result` field** (2 lines: batch info + headers JSON)
   - **Execute `COMMIT` to force immediate MySQL write** (enables real-time visibility in SQL clients)
   - **Save first batch payload to `first_batch_payload` field** + `COMMIT`
   - Wait 3 seconds between batches
   - Handle rate limits with exponential backoff
   - Log progress in real-time to temp_log and database
10. Append completion summary to `last_result` field + `COMMIT`

**Rate Limiting Logic:**
- Initial delay: 3 seconds between batches
- On 429 (rate limit): Retry after `Retry-After` header value
- Increase base delay by 1 second for remaining batches
- Max retries per batch: 3 attempts with exponential backoff (3s, 9s, 27s)

**Payload Format:**

The system uses the following payload format as documented in the [Klaviyo Custom Objects API](https://developers.klaviyo.com/en/reference/custom_objects_api_overview).

**✅ CONFIRMED**: Our payload structure **exactly matches** the official Klaviyo documentation example for "Bulk Create Data Source Records" (verified Nov 3, 2025).

**API Payload Structure:**
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
                "p_key": "UNIQUE-ID",
                "field1": "value1"
              }
            }
          }
        ]
      }
    },
    "relationships": {
      "data-source": {
        "data": {
          "type": "data-source",
          "id": "01K8Y5K8M7E0Q689TCVTMFR6TB"
        }
      }
    }
  }
}
```

**Comparison with Official Klaviyo Documentation:**

| Element | Our Implementation | Klaviyo Official Docs | Match |
|---------|-------------------|----------------------|-------|
| Endpoint | `POST /api/data-source-record-bulk-create-jobs` | Same | ✅ |
| Job Type | `data-source-record-bulk-create-job` | Same | ✅ |
| Records Path | `attributes.data-source-records.data` | Same | ✅ |
| Record Type | `data-source-record` | Same | ✅ |
| Record Data | `attributes.record` | Same | ✅ |
| Relationships | `relationships.data-source.data` | Same | ✅ |
| Data Source Type | `type: "data-source"` | Same | ✅ |
| Data Source ID | `id: "01K8Y5K8M7E0Q689TCVTMFR6TB"` | Same format | ✅ |

**Result**: Our payload format is **100% compliant** with Klaviyo's official API specification.

**Important Note from Klaviyo Documentation:**
> "This endpoint creates data source records asynchronously, so it may be a while (~15 minutes) before you notice data source records in your account."

This explains why HTTP 204 success responses don't guarantee immediate data visibility in the Klaviyo UI.

**Helper Functions:**
- `nce_klaviyo_request()` - Makes HTTP requests to Klaviyo API
- `nce_finish_and_log()` - Saves final results to database

---

#### `/src/nce_create_klaviyo_data_source_from_db.php` (108 lines)
**Purpose:** Creates new Klaviyo Data Source via API  
**Main Function:** `nce_create_klaviyo_data_source_from_db(): array`

**Workflow:**
1. Read API key and version from `wp_klaviyo_globals`
2. POST to `https://a.klaviyo.com/api/data-sources/`
3. Store returned Data Source ID and name back to database

**Payload:**
```json
{
  "data": {
    "type": "data-source",
    "attributes": {
      "name": "NCE Player Events Data Source"
    }
  }
}
```

**Critical Note:** `declare(strict_types=1);` MUST be on line 2 (immediately after `<?php`)

---

#### `/src/write_objects_old.php` (267 lines)
**Status:** Legacy reference file (DO NOT USE)  
**Purpose:** Original version with complex profile matching logic

**Key Differences from New Version:**
- Used `ID_field`, `object_match_field`, `contact_match_field` (removed)
- Included profile matching logic (no longer needed)
- Less sophisticated retry logic
- No real-time progress logging

---

### Documentation Files

#### `/KLAVIYO_SUPPORT_MEMO.md` (287 lines)
Comprehensive support memo for Klaviyo detailing data upload issue investigation.

#### `/README.md` (11 lines)
Basic project description and setup instructions.

---

## Database Schema

### Table: `wp_klaviyo_globals`

**Purpose:** Stores Klaviyo API configuration and job state

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `api_key` | VARCHAR(255) | Klaviyo Private API Key |
| `api_version` | VARCHAR(255) | API revision (e.g., "2025-04-15") |
| `object_ds_id` | VARCHAR(255) | Klaviyo Data Source ID |
| `object_ds_name` | VARCHAR(255) | Human-readable name |
| `object_query` | TEXT | Primary SQL query to fetch records (used when query_to_use=1 or null) |
| `object_query_2` | TEXT | Alternate SQL query #2 (used when query_to_use=2) |
| `object_query_3` | VARCHAR(255) | Alternate SQL query #3 (used when query_to_use=3) |
| `query_to_use` | SMALLINT | Which query to execute: 1=object_query (default), 2=object_query_2, 3=object_query_3 |
| `batch_size` | SMALLINT | Records per batch (default: 90, **optimal: 450**, range: 1-1000) |
| `batch_limit` | SMALLINT | Max batches per run (0 = unlimited) |
| `starting_offset` | INT | Starting offset for batch processing (default: 0) |
| `control_param_2` | VARCHAR(255) | Generic control parameter (currently unused, reserved for future features) |
| `updated_at` | TIMESTAMP | Last update timestamp |
| `last_result` | LONGTEXT | Running log of batch results (appended during execution) |
| `first_batch_payload` | LONGTEXT | JSON payload of first batch (for debugging) |

**New Fields (Added Nov 2-3, 2025):**
- `batch_size`: Configurable records per batch (default 90, **optimal 450** per Nov 3 testing). Allows testing with smaller batches (e.g., 5-10 records) or adjusting throughput. Must be between 1-1000. With 450 records/batch, full 17k dataset completes in 2-3 minutes.
- `starting_offset`: Allows resuming batch processing from a specific offset instead of starting at 0. Useful for resuming failed jobs or skipping already-processed records.
- `object_query_2`, `object_query_3`: Alternate SQL queries that can be selected via `query_to_use` field. Useful for testing different data sets or query variations without modifying the primary query.
- `query_to_use` *(renamed from control_param_1 on Nov 3)*: Selects which query to execute. **1 or null** = `object_query` (default), **2** = `object_query_2`, **3** = `object_query_3`. Only logged if value > 1.
- `control_param_2`: Generic control parameter (currently unused). Only reported in logs if value > 0. Reserved for future features.
- `first_batch_payload`: Stores the complete JSON payload of the first batch for debugging. Cleared at start of each job, populated after first batch is processed.
- `last_result`: **Changed behavior** - Now stores a running log of batch results appended during execution, rather than a final JSON summary.

**Example `object_query`:**
```sql
SELECT 
  CONCAT(sku, '|||', id) AS p_key,
  order_year, order_month, event_year, event_month,
  sku, family_email, price, person_type,
  first_name, last_name, yob, rating, gender, preferred_position
FROM wp_nce_player_events
ORDER BY id
```

**Example `last_result` (when job is queued):**
```json
{
  "status": "queued",
  "queue_status": "queued",
  "queued_at": "2025-11-02 15:30:00",
  "queued_via": "REST API",
  "remote_ip": "192.168.1.1",
  "next_scheduled_run": "2025-11-02 15:30:00",
  "payload": {"task": 2},
  "message": "Job queued via REST API, will execute asynchronously"
}
```

**Example `last_result` (during and after job execution):**
```
Cron job has started
[15:30:05] Batch size: 90 records per batch
[15:30:05] Batch 001/005 | Records: 90 | HTTP: 204 | Status: SUCCESS | Uploaded: 90 | Memory: 195.9MB | Klaviyo req id: ce5ef2aa-8ac6-45d6-a012-3e40ac646487 | Errors: 0
[15:30:05] Batch 001/005 | Headers: {"x-klaviyo-req-id":"ce5ef2aa-8ac6-45d6-a012-3e40ac646487","content-type":"application/json"}
[15:30:08] Batch 002/005 | Records: 90 | HTTP: 204 | Status: SUCCESS | Uploaded: 90 | Memory: 196.2MB | Klaviyo req id: 7f8e9d0c-1234-5678-abcd-ef1234567890 | Errors: 0
[15:30:08] Batch 002/005 | Headers: {"x-klaviyo-req-id":"7f8e9d0c-1234-5678-abcd-ef1234567890","content-type":"application/json"}
[15:30:11] Batch 003/005 | Records: 90 | HTTP: 204 | Status: SUCCESS | Uploaded: 90 | Memory: 196.5MB | Klaviyo req id: abc123-4567-890d-ef12-3456789abcde | Errors: 0
[15:30:11] Batch 003/005 | Headers: {"x-klaviyo-req-id":"abc123-4567-890d-ef12-3456789abcde","content-type":"application/json"}
[15:30:14] --- JOB COMPLETE ---
[15:30:14] Total batches: 3
[15:30:14] Total uploaded: 270
[15:30:14] Final delay: 3s
```

**New Behavior (Nov 2, 2025):** 
- `last_result` is now a running log, not JSON
- Each batch result is appended immediately after processing (2 lines per batch)
- Includes completion summary at the end
- **Real-time visibility:** Uses `SET autocommit=1` and explicit `COMMIT` statements after each database write
- External SQL clients (Navicat, MySQL Workbench, phpMyAdmin, etc.) can see updates immediately
- **Monitoring tip:** Use `wp db query "SELECT last_result FROM wp_klaviyo_globals ORDER BY id DESC LIMIT 1"` to watch progress in real-time

**Access via WP-CLI:**
```bash
# Get full configuration
wp option get klaviyo_globals

# View running log of batch results
wp db query "SELECT last_result FROM wp_klaviyo_globals ORDER BY id DESC LIMIT 1"

# View first batch payload (for debugging)
wp db query "SELECT first_batch_payload FROM wp_klaviyo_globals ORDER BY id DESC LIMIT 1"

# Update batch limit
wp db query "UPDATE wp_klaviyo_globals SET batch_limit = 10 ORDER BY id DESC LIMIT 1"

# Set starting offset to resume from record 1000
wp db query "UPDATE wp_klaviyo_globals SET starting_offset = 1000 ORDER BY id DESC LIMIT 1"

# Select which query to use (1=object_query, 2=object_query_2, 3=object_query_3)
wp db query "UPDATE wp_klaviyo_globals SET query_to_use = 2 ORDER BY id DESC LIMIT 1"
```

---

## Logging & Debugging

### Log Files

#### 1. `/wp-content/wp-custom-scripts/temp_log.log`
**Purpose:** Real-time batch progress (primary monitoring log)  
**Note:** Automatically cleared at the start of each job run

**Format:**
```
Cron job has started
[HH:MM:SS] Batch size: 90 records per batch
[HH:MM:SS] Batch 001/010 | Records: 90 | HTTP: 204 | Status: SUCCESS | Uploaded: 90 | Memory: 195.9MB | Klaviyo req id: ce5ef2aa-8ac6-45d6-a012-3e40ac646487 | Errors: 0
[HH:MM:SS] Batch 001/010 | Headers: {"x-klaviyo-req-id":"ce5ef2aa-8ac6-45d6-a012-3e40ac646487","content-type":"application/json",...}
[HH:MM:SS] Batch 002/010 | Records: 90 | HTTP: 204 | Status: SUCCESS | Uploaded: 90 | Memory: 196.2MB | Klaviyo req id: 7f8e9d0c-1234-5678-abcd-ef1234567890 | Errors: 0
[HH:MM:SS] Batch 002/010 | Headers: {"x-klaviyo-req-id":"7f8e9d0c-1234-5678-abcd-ef1234567890","content-type":"application/json",...}
```

**New Format (Nov 2, 2025):**
- Each batch result is now **2 lines**:
  1. Batch summary with status, uploaded count, Klaviyo request ID, and error count
  2. Full response headers as single-line JSON
- **Uploaded count** is set to 0 if batch fails
- Headers JSON has all newlines/carriage returns removed for single-line format

**When using custom batch_size:**
```
[HH:MM:SS] Batch size: 10 records per batch
[HH:MM:SS] NOTE: Using custom batch size (default is 90)
```

**Watch Live:**
```bash
ssh ncesoccerdev1.wpenginepowered.com "tail -f /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log"
```

**Key Metrics:**
- `Batch XXX/YYY` - Current batch / Total batches
- `HTTP: 204` - Klaviyo API response code (204 = success)
- `Status: SUCCESS|FAILED`
- `Uploaded: N` - Records uploaded in this batch (0 if failed)
- `Memory: X.XMB` - PHP memory usage
- `Klaviyo req id` - Klaviyo request ID for support tickets (from `x-klaviyo-req-id` header)
- `Errors: N` - Number of errors (0 = success, 1+ = failed)

---

#### 2. `/wp-content/nce-runner-debug.log`
**Purpose:** Plugin lifecycle events (cron job start/stop/errors)  
**Note:** Automatically cleared at the start of each job run

**Format:**
```
[2025-11-02 18:00:00] ============================================================
[2025-11-02 18:00:00] REST ENDPOINT CALLED: /wp-json/nce/v1/run
[2025-11-02 18:00:00] Timestamp: 2025-11-02 18:00:00
[2025-11-02 18:00:00] Remote IP: 192.168.1.1
[2025-11-02 18:00:00] Task requested: 2
[2025-11-02 18:00:00] Task 2: Cron job QUEUED successfully
[2025-11-02 18:00:00] Payload: {"task":2}
[2025-11-02 18:00:00] Next scheduled run: 2025-11-02 18:00:00
[2025-11-02 18:00:00] REST endpoint returning response (job will run asynchronously)
[2025-11-02 18:00:00] ------------------------------------------------------------
[2025-11-02 18:00:01] ============================================================
[2025-11-02 18:00:01] ===== NCE RUNNER: CRON JOB EXECUTION STARTED =====
[2025-11-02 18:00:01] ============================================================
[2025-11-02 18:00:01] Timestamp: 2025-11-02 18:00:01
[2025-11-02 18:00:01] Payload received: {...}
[2025-11-02 18:00:01] Loading klaviyo_write_objects.php
[2025-11-02 18:00:01] Function klaviyo_write_objects exists: true
[2025-11-02 18:00:01] Result from klaviyo_write_objects: {"status":"success",...}
[2025-11-02 18:00:18] ============================================================
[2025-11-02 18:00:18] ===== NCE RUNNER: CRON JOB EXECUTION ENDED =====
[2025-11-02 18:00:18] ============================================================
```

**Key Sections:**
1. **REST Endpoint Called** - Logs immediately when API is hit
2. **Cron Job Queued** - Logs when job is queued (API returns here)
3. **Cron Job Execution** - Logs when background job actually runs

**Watch Live:**
```bash
ssh ncesoccerdev1.wpenginepowered.com "tail -f /nas/content/live/ncesoccerdev1/wp-content/nce-runner-debug.log"
```

**Symlink Setup:**
```bash
# Delete old symlink (if pointed to wrong file)
rm /nas/content/live/ncesoccerdev1/wp-custom-scripts/nce-runner-debug.log

# Create symlink in wp-custom-scripts for easy access
cd /nas/content/live/ncesoccerdev1/wp-custom-scripts
ln -s ../wp-content/nce-runner-debug.log nce-runner-debug.log
```

---

#### 3. `/wp-content/debug.log` (WordPress Default)
**Purpose:** System-wide PHP errors from ALL plugins  
**Status:** IGNORE - Polluted by third-party plugins  
**Note:** NOT used by NCE plugin

---

### Debugging Strategy

#### Check if Cron Job is Queued
```bash
wp cron event list --field=hook | grep nce_runner_do_work
```

#### Run Cron Job Immediately
```bash
wp cron event run nce_runner_do_work
```

#### Check Database State
```bash
# Check last_result (shows queue info immediately, then job results)
wp option get klaviyo_globals --format=json | jq '.last_result'

# Pretty print the last result
wp option get klaviyo_globals --format=json | jq '.last_result' | jq -r . | jq .
```

**What you'll see:**
- **Right after triggering REST API:** Queue info (status: "queued", queued_at, remote_ip, etc.)
- **After job completes:** Job results (success/error, batches, uploaded records, etc.)

#### Clear "In Progress" Lock
```bash
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.last_result', '') WHERE option_name = 'klaviyo_globals'"
```

#### Monitor Both Logs Simultaneously
```bash
# Terminal 1
ssh ncesoccerdev1.wpenginepowered.com "tail -f /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log"

# Terminal 2  
ssh ncesoccerdev1.wpenginepowered.com "tail -f /nas/content/live/ncesoccerdev1/wp-content/nce-runner-debug.log"
```

---

## Klaviyo API Details

### Authentication
- **Method:** API Key in `Authorization` header
- **Format:** `Authorization: Klaviyo-API-Key pk_abc123...`
- **Versioning:** `revision` header (e.g., `revision: 2025-04-15`)

### Endpoints Used

#### 1. Create Data Source
**Endpoint:** `POST https://a.klaviyo.com/api/data-sources/`  
**Purpose:** Initial setup - creates container for records

**Request:**
```json
{
  "data": {
    "type": "data-source",
    "attributes": {
      "name": "Your Data Source Name"
    }
  }
}
```

**Response:**
```json
{
  "data": {
    "type": "data-source",
    "id": "01K8Y5K8M7E0Q689TCVTMFR6TB",
    "attributes": {
      "name": "Your Data Source Name"
    }
  }
}
```

---

#### 2. Bulk Create Records (Primary Upload)
**Endpoint:** `POST https://a.klaviyo.com/api/data-source-record-bulk-create-jobs`  
**Purpose:** Upload batches of records

**Critical Details:**
- **HTTP 204 = Success** (empty body, no Job ID in response)
- Job ID available in `Location` header (if provided)
- Use `x-klaviyo-req-id` from response headers for support tickets
- Records are queued asynchronously (not immediate)

**Request Structure:**
```json
{
  "data": {
    "type": "data-source-record-bulk-create-job",
    "attributes": {
      "records": [
        {
          "type": "data-source-record",
          "attributes": {
            "record": {
              "field1": "value1",
              "field2": "value2"
            }
          }
        }
      ]
    },
    "relationships": {
      "data-source": {
        "data": {
          "type": "data-source",
          "id": "YOUR_DATA_SOURCE_ID"
        }
      }
    }
  }
}
```

**Important Notes:**
1. **No Primary Key Designation:** The `p_key` field is just another data field. Primary key is configured in Klaviyo's UI.
2. **No Relationship Specs:** Profile matching (email/phone) is configured in Klaviyo's UI, not in API payload.
3. **Batch Size:** Recommended 90 records per batch (Klaviyo limits vary by plan).
4. **Rate Limits:** Watch for HTTP 429 responses with `Retry-After` header.

---

#### 3. Get Records (Verification)
**Endpoint:** `GET https://a.klaviyo.com/api/data-source-records/`  
**Purpose:** Verify uploaded data

**Query Parameters:**
- `filter=equals(data_source_id,"YOUR_ID")`
- `page[size]=500` (max per page)
- `page[marker]=NEXT_PAGE_TOKEN` (pagination)

**Response Headers:**
- `ratelimit-limit` - Total requests allowed
- `ratelimit-remaining` - Requests left in window
- `ratelimit-reset` - Unix timestamp when limit resets

---

### Rate Limiting

| Response Code | Meaning | Action |
|---------------|---------|--------|
| 200/204 | Success | Continue |
| 400 | Bad Request | Log error, stop batch |
| 401 | Invalid API Key | Stop job, fix credentials |
| 429 | Rate Limited | Retry after `Retry-After` seconds, increase delay |
| 500 | Server Error | Retry with exponential backoff |

**Retry Logic:**
```
Attempt 1: Wait Retry-After seconds (or 3s default)
Attempt 2: Wait 9 seconds
Attempt 3: Wait 27 seconds
After 3 failures: Stop job, log error
```

**Dynamic Delay Adjustment:**
If ANY batch gets rate-limited, increase base delay by 1 second for all remaining batches in the job.

---

## Common Issues & Solutions

### Issue 1: Fatal Error - `declare(strict_types=1)` Placement
**Error Message:**
```
Fatal error: strict_types declaration must be the very first statement in the script
```

**Cause:** `declare(strict_types=1);` was not immediately after `<?php`

**Solution:**
```php
<?php
declare(strict_types=1);
// Comments and code below
```

**Files Affected:** `nce_create_klaviyo_data_source_from_db.php`

---

### Issue 2: HTTP 404 - Endpoint Not Found
**Error Message:**
```
"The path /api/data-source-records/bulk-create could not be found"
```

**Cause:** Incorrect endpoint URL (missing `-jobs` suffix)

**Solution:** Change endpoint to:
```
https://a.klaviyo.com/api/data-source-record-bulk-create-jobs
```

---

### Issue 3: HTTP 400 - "An object with data is required"
**Cause:** Payload structure incorrect

**Wrong Structure:**
```json
{
  "records": [...],
  "data_source": {...}
}
```

**Correct Structure:**
```json
{
  "data": {
    "type": "data-source-record-bulk-create-job",
    "attributes": {
      "records": [...]
    },
    "relationships": {
      "data-source": {...}
    }
  }
}
```

---

### Issue 4: Empty `last_result` Despite Success
**Symptoms:**
- Logs show "Database updated successfully"
- `wp_klaviyo_globals.last_result` remains empty string

**Cause:** `wp_json_encode()` returning empty string

**Solution:** Added detailed logging in `nce_finish_and_log()`:
```php
$json = wp_json_encode($summary, JSON_PRETTY_PRINT);
nce_debug_log("JSON encode result length: " . strlen($json));
if (empty($json)) {
    nce_debug_log("ERROR: JSON encode returned empty string!");
}
```

---

### Issue 5: Job Never Completes (Large Batch Limits)
**Symptoms:**
- `last_result` stuck on "In Progress"
- No new log entries
- `batch_limit` set high (e.g., 180+)

**Causes:**
1. PHP memory exhaustion
2. Execution timeout
3. Silent crash

**Solutions:**
1. Increased `memory_limit` to 512MB
2. Set `max_execution_time` to 1800 seconds (30 min)
3. Added progress logging every 10 batches
4. Added checkpoint saves to DB every 50 batches
5. Added memory usage tracking in logs

---

### Issue 6: REST API Cluttered with Third-Party Errors
**Symptoms:**
```html
<div style='background: #ffebee;'>PHP Error: Function _load_textdomain_just_in_time...</div>
{"ok":true,"task":2}
```

**Cause:** WordPress/WooCommerce errors leaking into REST response

**Solution:** Simplified REST handler to use `wp_send_json()` and `exit` immediately:
```php
wp_send_json(['ok' => true, 'task' => 2, 'status' => 'queued'], 200);
exit;
```

---

### Issue 7: Debug Log Polluted with Other Plugins
**Symptoms:**
- `nce-runner-debug.log` full of WooCommerce/Zoho warnings
- Timestamps in format `[01-Nov-2025 17:57:04 UTC]` (not NCE format)

**Cause:** Symlink pointed to `wp-content/debug.log` instead of separate file

**Solution:**
```bash
# Delete symlink
rm /nas/content/live/ncesoccerdev1/wp-custom-scripts/nce-runner-debug.log

# Create proper symlink
cd /nas/content/live/ncesoccerdev1/wp-custom-scripts
ln -s ../wp-content/nce-runner-debug.log nce-runner-debug.log
```

**Code Change:** Removed `error_log($message)` call from `nce_debug_log()` function.

---

### Issue 8: Data Not Appearing in Klaviyo UI
**Symptoms:**
- HTTP 204 responses (success)
- `x-klaviyo-req-id` in logs
- Zero records in Klaviyo UI

**Status:** UNRESOLVED - Under investigation with Klaviyo support

**Evidence Collected:**
- 450+ successful batch uploads
- All HTTP 204 responses
- Request IDs logged
- Sample payload logged
- Different `p_key` values per batch (verified not uploading duplicates)

**Next Steps:**
1. Use `verify_klaviyo_data.php` script to check GET API
2. Open support ticket with Klaviyo using KLAVIYO_SUPPORT_MEMO.md
3. Check Data Source configuration in Klaviyo UI

---

### Issue 9: Uploading Same Records Repeatedly
**Symptoms:** Concern that batch offset wasn't incrementing

**Diagnosis:** Added logging for first 3 batches:
```
Batch 1: First p_key: TRA-TRY-GK-NY-OPHIR-WINTER-25-26-1|||94915
Batch 2: First p_key: TRA-OF-CA-BAYSIDE-FALL-25|||94520
Batch 3: First p_key: (different value)
```

**Result:** CONFIRMED - Different records per batch. Offset logic working correctly.

---

### Issue 10: Resuming Failed Jobs
**Use Case:** Job crashed or stopped at batch 50 (offset 4500)

**Solution:** Use `starting_offset` field to resume from where it left off:

```bash
# Calculate offset: (last_successful_batch × batch_size)
# Example: Batch 50 with batch_size=90 = offset 4500

# Set starting offset
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.starting_offset', 4500) WHERE option_name = 'klaviyo_globals'"

# Clear "In Progress" lock
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.last_result', '') WHERE option_name = 'klaviyo_globals'"

# Re-run job
wp cron event run nce_runner_do_work

# After successful completion, reset offset to 0
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.starting_offset', 0) WHERE option_name = 'klaviyo_globals'"
```

**Log Output:**
```
[15:30:12] JOB STARTED
[15:30:12] Batch size: 90 records per batch
[15:30:12] Using starting_offset: 4500
[15:30:15] Batch 001/050 | Records: 90 | HTTP: 204 | Status: SUCCESS | Uploaded: 90 | ...
```

---

### Issue 11: Testing with Small Batch Sizes
**Use Case:** Want to test the upload process with just 10-20 records before running full job

**Solution:** Temporarily reduce `batch_size` for testing:

```bash
# Set small batch size for testing
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.batch_size', 10) WHERE option_name = 'klaviyo_globals'"

# Limit to 2 batches (only 20 records total)
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.batch_limit', 2) WHERE option_name = 'klaviyo_globals'"

# Run test
wp cron event run nce_runner_do_work

# After testing, restore defaults
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.batch_size', 90) WHERE option_name = 'klaviyo_globals'"
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.batch_limit', 0) WHERE option_name = 'klaviyo_globals'"
```

**Log Output:**
```
[16:45:10] JOB STARTED
[16:45:10] Batch size: 10 records per batch
[16:45:10] NOTE: Using custom batch size (default is 90)
[16:45:10] First batch payload sample:
  Batch Size: 10 records
  Records in this batch: 10
[16:45:13] Batch 001/002 | Records: 10 | HTTP: 204 | Status: SUCCESS | Uploaded: 10 | ...
[16:45:16] Batch 002/002 | Records: 10 | HTTP: 204 | Status: SUCCESS | Uploaded: 20 | ...
[16:45:16] JOB COMPLETED SUCCESSFULLY - Total: 2 batches, 20 records
```

---

### Issue 12: Testing Alternate Payload Format ✅ RESOLVED & REMOVED

**Background:** 
Data uploads successfully (HTTP 204) but doesn't appear in Klaviyo UI. To rule out payload format issues, an alternate payload structure was tested (Nov 2, 2025).

**Test Results:** 
- **Current format**: `attributes.data-source-records.data` wrapper ✅ WORKS (HTTP 204)
- **Alternate format**: `attributes.records` direct array ❌ FAILS (HTTP 400)

**Error from Alternate Format:**
```
'records' is not a valid field for the resource 'data-source-record-bulk-create-job'.
```

**Conclusion:** 
The current payload format is **100% CORRECT** and matches official Klaviyo documentation. The data visibility issue is NOT related to payload structure.

**Code Changes (Nov 3, 2025):**
- ✅ **Removed** alternate payload code branch from `klaviyo_write_objects.php`
- ✅ Simplified code to only use the correct, documented payload format
- ✅ **Renamed** `control_param_1` to `query_to_use` - now selects which SQL query to execute (1=object_query, 2=object_query_2, 3=object_query_3)
- ✅ Added `object_query_2` and `object_query_3` fields for alternate query testing
- ✅ `control_param_2` remains in database for future use but is currently unused

**Next Steps for Data Visibility Issue:**
Since payload structure is confirmed correct per official docs, the issue must be:
1. Data Source configuration in Klaviyo UI (primary key, profile mapping)
2. Klaviyo's async processing delay (check after 24-48 hours, ~15 minutes per docs)
3. Klaviyo internal issue (requires support ticket)

---

## Deployment & Operations

### Initial Setup

#### 1. SSH Access
```bash
ssh ncesoccerdev1.wpenginepowered.com
```

**Note:** Certificate/key already installed, no username required.

---

#### 2. Install Plugin
```bash
# Upload files to
/nas/content/live/ncesoccerdev1/wp-content/plugins/nce-klaviyo-integration/

# Activate plugin
wp plugin activate nce-klaviyo-integration
```

---

#### 3. Configure Database
```bash
wp option update klaviyo_globals '{
  "api_key": "pk_YOUR_KEY_HERE",
  "api_version": "2025-04-15",
  "data_source_id": "",
  "data_source_name": "",
  "object_query": "SELECT ... FROM wp_nce_player_events ORDER BY id",
  "batch_limit": 5,
  "last_result": ""
}' --format=json
```

---

#### 4. Create Data Source
```bash
# Run creation script via WP-CLI or REST API
# This populates data_source_id and data_source_name
```

---

### Running Jobs

#### Trigger via REST API
```bash
curl -X POST https://ncesoccerdev1.wpenginepowered.com/wp-json/nce/v1/run \
  -H "Content-Type: application/json" \
  -d '{"task": 2}'
```

**Note:** This call returns immediately after queueing the job. Check `nce-runner-debug.log` to see:
1. When the REST endpoint was called
2. When the cron job was queued
3. When the background job actually starts (happens seconds later)

---

#### Trigger via WP-CLI
```bash
ssh ncesoccerdev1.wpenginepowered.com "wp cron event run nce_runner_do_work"
```

---

#### Monitor Progress
```bash
# Terminal 1: Watch batch progress
ssh ncesoccerdev1.wpenginepowered.com "tail -f /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log"

# Terminal 2: Watch plugin events
ssh ncesoccerdev1.wpenginepowered.com "tail -f /nas/content/live/ncesoccerdev1/wp-content/nce-runner-debug.log"

# Terminal 3: Watch database status (polls every 2 seconds)
watch -n 2 'wp option get klaviyo_globals --format=json | jq -r ".last_result" | jq .'
```

---

#### Abort Running Job
```bash
# Kill PHP process (WP Engine will restart it)
ssh ncesoccerdev1.wpenginepowered.com "pkill -f nce_klaviyo_write_objects"

# Clear "In Progress" lock
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.last_result', '') WHERE option_name = 'klaviyo_globals'"
```

---

### Maintenance Commands

#### Check Cron Queue
```bash
wp cron event list --field=hook | grep nce
```

#### View Last Result
```bash
wp option get klaviyo_globals --format=json | jq '.last_result'
```

#### Set Batch Size (Records Per Batch)
```bash
# Set to 10 records per batch for testing
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.batch_size', 10) WHERE option_name = 'klaviyo_globals'"

# Reset to default (90 records)
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.batch_size', 90) WHERE option_name = 'klaviyo_globals'"

# Set to optimal 450 records for production (tested Nov 3, 2025 - completes 17k in 2-3 minutes)
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.batch_size', 450) WHERE option_name = 'klaviyo_globals'"
```

#### Update Batch Limit (Max Batches)
```bash
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.batch_limit', 10) WHERE option_name = 'klaviyo_globals'"
```

#### Set Starting Offset (Resume Job)
```bash
# Resume from record 1800 (e.g., if job failed at batch 20)
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.starting_offset', 1800) WHERE option_name = 'klaviyo_globals'"

# Reset to start from beginning
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.starting_offset', 0) WHERE option_name = 'klaviyo_globals'"
```

#### Select Which Query to Use
```bash
# Use primary query (object_query) - DEFAULT
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.query_to_use', 1) WHERE option_name = 'klaviyo_globals'"

# Use alternate query #2 (object_query_2)
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.query_to_use', 2) WHERE option_name = 'klaviyo_globals'"

# Use alternate query #3 (object_query_3)
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.query_to_use', 3) WHERE option_name = 'klaviyo_globals'"

# Reset to default (primary query)
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.query_to_use', 1) WHERE option_name = 'klaviyo_globals'"
```

**Use Case:** Test different data sets or query variations without modifying the primary `object_query`. Store up to 3 different queries and switch between them using `query_to_use`.

#### Set Control Parameter 2 (Currently Unused - Reserved for Future Features)
```bash
# Set control_param_2 (generic parameter, currently unused)
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.control_param_2', '1') WHERE option_name = 'klaviyo_globals'"

# Reset to 0 (will not be reported in logs)
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.control_param_2', '0') WHERE option_name = 'klaviyo_globals'"
```

#### Clear Logs
**Note:** Logs are automatically cleared at the start of each cron job run.

Manual clearing (if needed):
```bash
echo "Log cleared at $(date)" > /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log
echo "Log cleared at $(date)" > /nas/content/live/ncesoccerdev1/wp-content/nce-runner-debug.log
```

#### Deactivate/Reactivate Plugin
```bash
wp plugin deactivate nce-klaviyo-integration
wp plugin activate nce-klaviyo-integration
```

---

## CLI Commands Reference

**Platform:** All commands are **Linux/Unix commands** compatible with WP Engine's Linux servers.  
**SSH Required:** Most commands require SSH access to the production server.  
**WP-CLI:** WordPress CLI (`wp`) is pre-installed on WP Engine servers.

### Common Linux Commands Quick Reference

| Command | Purpose | Example |
|---------|---------|---------|
| `tail -f <file>` | Watch file in real-time | `tail -f temp_log.log` |
| `tail -n 50 <file>` | Show last 50 lines | `tail -50 temp_log.log` |
| `head -n 20 <file>` | Show first 20 lines | `head -20 temp_log.log` |
| `wc -l <file>` | Count lines in file | `wc -l temp_log.log` |
| `grep <pattern> <file>` | Search for pattern | `grep "error" temp_log.log` |
| `grep -i <pattern>` | Case-insensitive search | `grep -i "ERROR" temp_log.log` |
| `grep -E <regex>` | Extended regex search | `grep -E "Batch [0-9]+"` |
| `cat <file>` | Display entire file | `cat temp_log.log` |
| `cat -n <file>` | Display with line numbers | `cat -n temp_log.log` |
| `ls -lh <file>` | Show file size | `ls -lh temp_log.log` |
| `> <file>` | Clear/empty file | `> temp_log.log` |
| `less <file>` | View file with pagination | `less temp_log.log` |
| `find <path> -name` | Find files by name | `find . -name "*.log"` |

**Tip:** Press `Ctrl+C` to exit `tail -f` or `less` commands.

---

### Production Workflow (Task 3)
```bash
# Trigger upload via REST API (waits for completion)
curl -X POST https://ncesoccerdev1.wpenginepowered.com/wp-json/nce/v1/run \
  -H "Content-Type: application/json" \
  -d '{"task": 3}'

# Response includes complete result (after 2-3 minutes):
# {
#   "ok": true,
#   "task": 3,
#   "status": "completed",
#   "duration_seconds": 125.4,
#   "result": {
#     "success": true,
#     "message": "Job completed - see last_result field for details"
#   }
# }

# Optional: View detailed logs after completion
ssh ncesoccerdev1.wpenginepowered.com
cat /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log
cat /nas/content/live/ncesoccerdev1/wp-content/nce-runner-debug.log

# Or check database for batch-by-batch progress
wp db query "SELECT last_result FROM wp_klaviyo_globals ORDER BY id DESC LIMIT 1"
```

---

### Plugin Management
```bash
# Activate
wp plugin activate nce-klaviyo-integration

# Deactivate  
wp plugin deactivate nce-klaviyo-integration

# List all plugins
wp plugin list
```

---

---

### Database Queries
```bash
# Get full config
wp option get klaviyo_globals --format=json

# Get specific field (requires jq)
wp option get klaviyo_globals --format=json | jq '.api_version'

# Update specific field
wp db query "UPDATE wp_options SET option_value = JSON_SET(option_value, '$.batch_limit', 50) WHERE option_name = 'klaviyo_globals'"

# View table structure
wp db query "DESCRIBE wp_klaviyo_globals"

# Count records in source table
wp db query "SELECT COUNT(*) FROM wp_nce_player_events"
```

---

### Log Analysis

**Note:** All commands below are **Linux commands** (WP Engine servers run Linux). These work on the production server via SSH.

```bash
# Watch live logs (updates in real-time)
tail -f /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log

# Last 50 lines
tail -50 /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log

# First 20 lines
head -20 /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log

# Search for errors (case-insensitive)
grep -i error /nas/content/live/ncesoccerdev1/wp-content/nce-runner-debug.log

# Count successful batches
grep "Status: SUCCESS" /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log | wc -l

# Count total lines in log
wc -l /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log

# Extract all Klaviyo request IDs
grep "x-klaviyo-req-id" /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log

# Show only batch numbers and status
grep -E "Batch [0-9]+" /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log | grep -E "SUCCESS|FAILED"

# Search for specific batch number
grep "Batch 10:" /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log

# View logs with line numbers
cat -n /nas/content/live/ncesoccerdev1/wp-content/nce-runner-debug.log | tail -50

# Show file size
ls -lh /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log

# Clear log file (empties it)
> /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log
# OR
echo "" > /nas/content/live/ncesoccerdev1/wp-content/wp-custom-scripts/temp_log.log
```

---

## Architecture Notes

### Why Synchronous Execution?

**Performance Benchmark (Nov 3, 2025):**
- **Batch size: 450 records** → ~5 seconds per batch
- **Full dataset: 17,000 records** → ~38 batches → **2-3 minutes total**
- **Klaviyo async processing:** ~15 minutes after API calls complete

**Task 3 (Synchronous Upload):**
- ✅ **Immediate feedback** - REST response includes complete result
- ✅ **Simple monitoring** - No need to check logs or poll database
- ✅ **Fast completion** - 2-3 minutes for 17k records
- ✅ **Klaviyo handles async processing** - HTTP 204 returns immediately, Klaviyo processes in background
- ✅ **No WP-Cron complexity** - Runs directly in REST request
- ✅ **Real-time logging** - All logs accessible immediately after completion

**Why No WP-Cron (Task 2 removed):**
- Klaviyo's API already handles async processing on their end
- No benefit to queueing jobs in WordPress when Klaviyo queues them anyway
- Synchronous execution completes in 2-3 minutes (acceptable for REST)
- Simpler architecture with immediate feedback

### Batch Size Optimization

**Historical Default: 90 records**
- Conservative default to avoid rate limits
- Safe for all Klaviyo plan tiers
- Small enough to retry on failure without losing much progress

**Tested Optimal: 450 records** ⭐ (Nov 3, 2025)
- ✅ **~5 seconds per batch** (very fast performance)
- ✅ **No rate limit issues** observed during testing
- ✅ **Full 17k dataset in 2-3 minutes** (~38 batches)
- ✅ **Perfect for Task 3** (synchronous execution with immediate feedback)
- ✅ 5x faster than default batch size

**How to Set Batch Size:**
```sql
-- Set to optimal 450 for production
UPDATE wp_klaviyo_globals SET batch_size = 450 WHERE id = 1;

-- Or set to 10 for quick testing
UPDATE wp_klaviyo_globals SET batch_size = 10 WHERE id = 1;
```

**Configurable Range:** 1-1000 records per batch (validated in code)

### Why 3-Second Delays?
- Prevents hitting rate limits on free/low-tier Klaviyo plans
- Allows Klaviyo's async processing to keep up
- Increased dynamically if rate limited

### Why Two Log Files?
- `temp_log.log`: High-frequency, detailed batch progress (can grow large)
- `nce-runner-debug.log`: Low-frequency lifecycle events (stays small)
- Separation allows easy monitoring without log pollution

---

### Monitoring & Logging

**Best Practices:**
- **Monitor via REST response** - Complete result returned after job finishes
- **Check logs for details** - View batch-by-batch progress after completion
- **Database field `last_result`** - Contains running log of all batches

**Where to Find Logs:**
- `temp_log.log` - Detailed batch progress (batch numbers, status, memory usage)
- `nce-runner-debug.log` - Plugin lifecycle events (job start/stop, errors)
- Database field `last_result` - Same content as temp_log, persisted

**Example Workflow:**
```bash
# 1. Trigger upload
curl -X POST https://your-site.com/wp-json/nce/v1/run -d '{"task": 3}'

# 2. Wait for response (2-3 minutes)
# Response includes success/error and duration

# 3. Optional: View detailed logs
ssh your-server
cat /path/to/temp_log.log
```

---

## Known Limitations

1. **No Job Cancellation UI:** Must kill PHP process to abort running jobs
2. **No Real-Time Progress:** Can't see progress during execution (must wait 2-3 minutes for completion)
3. **No Email Notifications:** Relies on checking REST response
4. **Single Job at a Time:** No concurrent batch processing
5. **No Automatic Retry on Crash:** Must manually re-run with `starting_offset` to resume
6. **Klaviyo Async Processing:** Records take ~15 minutes to appear in Klaviyo UI after API calls complete (this is normal Klaviyo behavior)

---

## Future Improvements

1. **Admin UI:** WordPress dashboard for configuration and monitoring
2. **Job Queue:** Support multiple queued jobs
3. **Email Notifications:** Send summary on completion/failure
4. **Automatic Resume on Crash:** Auto-detect last successful batch and set `starting_offset`
5. **Additional Control Parameters:** Expand `control_param_2` for more conditional logic options
6. **Job Status Polling:** Query Klaviyo for job completion status
7. **Webhooks:** Receive Klaviyo notifications when processing completes
8. **Data Validation:** Pre-flight checks before upload
9. **Performance Metrics:** Track upload speed, memory usage over time
10. **Payload Format Auto-Detection:** Automatically determine which format works based on Klaviyo responses

---

## Support & Troubleshooting

### If Job Doesn't Start
1. Check cron is queued: `wp cron event list | grep nce`
2. Check `nce-runner-debug.log` for errors
3. Verify plugin is active: `wp plugin list`
4. Check `last_result` isn't stuck on "In Progress"

### If Job Starts But Fails
1. Check `temp_log.log` for HTTP error codes
2. Look for "ERROR:" in batch logs
3. Verify API key is valid
4. Check Klaviyo account rate limits
5. Verify Data Source ID exists in Klaviyo

### If Job Completes But Data Missing
1. Check `x-klaviyo-req-id` values in logs
2. Verify different `p_key` values in first 3 batches
3. Check Klaviyo Data Source configuration (primary key, profile matching)
4. Use `verify_klaviyo_data.php` to query GET API
5. Contact Klaviyo support with request IDs

---

## Important Contacts & Resources

**Klaviyo Documentation:**
- [Custom Objects API Overview](https://developers.klaviyo.com/en/reference/custom_objects_api_overview) - **Primary reference for payload format**
- [Data Sources API](https://developers.klaviyo.com/en/reference/data-sources)
- [Bulk Record Creation](https://developers.klaviyo.com/en/reference/create_data_source_record_bulk_create_job)
- [Rate Limits](https://developers.klaviyo.com/en/docs/rate_limits)

**Server Access:**
- Host: ncesoccerdev1.wpenginepowered.com
- Method: SSH with certificate authentication
- WP Engine Dashboard: [wpengine.com](https://wpengine.com)

**Plugin Version:** 2.0.0  
**Last Updated:** November 2, 2025  
**Last Successful Upload:** 270 records (3 batches) on Nov 1, 2025 22:12:05

---

## Quick Diagnostic Checklist

Use this when troubleshooting issues:

- [ ] Plugin is active (`wp plugin list`)
- [ ] `api_key` is set in `wp_klaviyo_globals`
- [ ] `data_source_id` is set in `wp_klaviyo_globals`
- [ ] `object_query` returns results (`wp db query "YOUR_QUERY LIMIT 1"`)
- [ ] `last_result` is not stuck on "In Progress"
- [ ] Cron job exists (`wp cron event list | grep nce`)
- [ ] No PHP fatal errors in `nce-runner-debug.log`
- [ ] Batches showing HTTP 204 in `temp_log.log`
- [ ] Different `p_key` values in first 3 batches
- [ ] `x-klaviyo-req-id` present in API responses
- [ ] Memory usage below 512MB
- [ ] No "ERROR:" strings in logs

---

*End of Project Handoff Documentation*

