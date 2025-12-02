# Task 7 & 8: SMS Consent Backfill Guide

**Last Updated:** 2025-11-28 19:05:00

## Overview

SMS consent backfill is split into **2 separate tasks** to work within WPEngine's 60-second timeout:

- **Task 7:** Fetch ALL profiles from Klaviyo & cache to database (~40 seconds)
- **Task 8:** Process cached profiles in batches (~25-30 seconds per run)

## Workflow

### Step 1: Fetch & Cache (Task 7)

**Run ONCE to fetch and cache all 8K+ profiles:**

```
https://your-site.com/wp-json/nce/v1/run?task=7
```

**What it does:**
- Fetches ALL profiles with phone numbers from Klaviyo
- Validates US phone format (`+1XXXXXXXXXX`)
- Saves to `wp_klaviyo_globals.control_param` (default job)
- Takes ~40 seconds

**Response:**
```json
{
  "success": true,
  "message": "Successfully cached 8169 profiles...",
  "valid_us_phones_cached": 8169,
  "estimated_batches": 82,
  "next_step": "?task=8&start_from=1"
}
```

**Optional Parameters:**
- `?task=7&refresh=true` - Force re-fetch even if cache exists
- `?task=7&job_name=default` - Use different job config (default: 'default')

---

### Step 2: Process Batches (Task 8)

**Run MULTIPLE TIMES to process all batches:**

#### Run 1: Batches 1-25
```
https://your-site.com/wp-json/nce/v1/run?task=8&start_from=1
```
Takes ~25-30 seconds

#### Run 2: Batches 26-50
```
https://your-site.com/wp-json/nce/v1/run?task=8&start_from=26
```

#### Run 3: Batches 51-75
```
https://your-site.com/wp-json/nce/v1/run?task=8&start_from=51
```

#### Run 4: Batches 76-82 (final)
```
https://your-site.com/wp-json/nce/v1/run?task=8&start_from=76
```

**What it does:**
- Reads cached profiles from database (instant)
- Processes 25 batches of 100 profiles each (2,500 profiles per run)
- Grants SMS marketing consent via Klaviyo API
- Deduplicates phone numbers within each batch

**Response:**
```json
{
  "success": true,
  "message": "SMS consent run complete - 57 batches remaining",
  "batches_processed_this_run": 25,
  "profiles_granted": 2500,
  "complete": false,
  "next_run": "?task=8&start_from=26"
}
```

When complete:
```json
{
  "success": true,
  "message": "SMS consent backfill COMPLETE - all batches processed!",
  "complete": true
}
```

**Optional Parameters:**
- `?task=8&start_from=1` - Start from batch # (default: 1)
- `?task=8&max_batches=25` - How many batches per run (default: 25)
- `?task=8&job_name=default` - Which job config to use (default: 'default')

---

## Quick Workflow Summary

```bash
# 1. Fetch & cache (run once)
?task=7

# 2. Process in chunks (run 4 times)
?task=8&start_from=1
?task=8&start_from=26
?task=8&start_from=51
?task=8&start_from=76
```

**Total time:** ~2-3 minutes (5 requests total)

---

## Parameters Reference

### Task 7 (Fetch & Cache)
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `job_name` | string | `default` | Job config to use |
| `refresh` | boolean | `false` | Force re-fetch if cache exists |

### Task 8 (Process Batches)
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `job_name` | string | `default` | Job config to use |
| `start_from` | integer | `1` | Which batch number to start from |
| `max_batches` | integer | `25` | Max batches to process this run |

---

## Troubleshooting

### "No cached profiles found"
**Solution:** Run Task 7 first

### "Timeout after 60 seconds"
**Solution:** Reduce `max_batches` parameter (try 15 or 20)

### "Duplicate phone number error"
**Solution:** Already handled! Script deduplicates within each batch automatically

### Want to start over?
**Solution:** Run `?task=7&refresh=true` to re-fetch and rebuild cache

---

## Technical Details

- **Cache Location:** `wp_klaviyo_globals.control_param` (job_name: 'default')
- **Batch Size:** 100 profiles per Klaviyo API call
- **Rate Limiting:** 0.5 seconds between batches
- **Phone Validation:** US format only (`+1XXXXXXXXXX`)
- **Deduplication:** Per-batch automatic deduplication
- **Timeout Safety:** Task 8 designed to fit in <60 seconds

---

## Files

- **Task 7:** `/wp-content/wp-custom-scripts/nce-runner-task-7/fetch_and_cache_profiles.php`
- **Task 8:** `/wp-content/wp-custom-scripts/nce-runner-task-8/process_cached_profiles.php`
- **Task Manager:** `/wp-content/wp-custom-scripts/nce-runner_task_manager.php`

---

## Version History

- **v3.0.0** (2025-11-28): Task 7 - Fetch & cache only
- **v1.0.0** (2025-11-28): Task 8 - Process cached profiles with chunking

