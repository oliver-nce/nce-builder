# Task 4: Grant Consent for NEW Profiles

This folder contains two scripts that grant marketing consent **only to recently created profiles**.

---

## Overview

**Task 5:** `grant_email_consent.php` - Grant email marketing consent  
**Task 6:** `grant_sms_consent.php` - Grant SMS marketing consent

Both scripts use a **lookback window** to target only NEW profiles, assuming existing profiles already have correct consent settings.

---

## Key Features

### 1. Lookback Window
- Default: **48 hours** (last 2 days)
- Configurable via `lookback_hours` parameter
- Only processes profiles created within the window
- Assumes older profiles are already configured correctly

### 2. Timestamp Detection
Scripts automatically check for these fields to determine profile age:
- `created_at` (preferred)
- `created`
- `inserted_at`

Profiles without a timestamp are skipped.

### 3. Same Query as Task 3
Both scripts use the same SQL query from `wp_klaviyo_globals` as the bulk upsert task, but filter to recent profiles only.

---

## Usage

### Grant Email Consent (Task 5)

```bash
POST /wp-json/nce-runner/v1/run-task
{
  "task": 5,
  "job_name": "profiles",
  "lookback_hours": 48
}
```

**What it does:**
- Fetches all profiles from configured query
- Filters to profiles created in last 48 hours (or specified window)
- Filters to profiles with valid email addresses
- Grants email marketing consent
- Skips older profiles (assumes already configured)

---

### Grant SMS Consent (Task 6)

```bash
POST /wp-json/nce-runner/v1/run-task
{
  "task": 6,
  "job_name": "profiles",
  "lookback_hours": 48
}
```

⚠️ **LEGAL WARNING:** Only run if you have explicit SMS opt-in checkboxes

**What it does:**
- Fetches all profiles from configured query
- Filters to profiles created in last 48 hours (or specified window)
- Filters to profiles with valid phone numbers (E.164 format)
- Grants SMS marketing consent
- Skips older profiles (assumes already configured)

---

## Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `task` | int | required | 5 for email, 6 for SMS |
| `job_name` | string | 'profiles' | Name in wp_klaviyo_globals table |
| `lookback_hours` | int | 48 | Hours to look back for new profiles |

---

## Lookback Examples

| lookback_hours | Looks Back | Use Case |
|----------------|------------|----------|
| 1 | Last hour | Real-time processing after signup |
| 24 | Last day | Daily batch run |
| 48 | Last 2 days | Default - safe buffer for delays |
| 168 | Last week | Initial historical run |
| 720 | Last 30 days | One-time backfill |

---

## When to Run These Scripts

### Scenario 1: After Bulk Profile Upsert (Task 3)
```
1. Run Task 3 (bulk upsert) - creates/updates profiles
2. Wait for completion
3. Run Task 5 (email consent) with lookback matching upsert window
4. Run Task 6 (SMS consent) if explicit opt-in exists
```

### Scenario 2: Scheduled Daily Run
Set up cron job to run Tasks 5 & 6 daily with `lookback_hours: 24`

This catches any new profiles created in last 24 hours.

### Scenario 3: Real-Time After Signup
Trigger immediately after new profile creation with `lookback_hours: 1`

---

## Response Format

```json
{
  "success": true,
  "message": "Email consent grant completed for new profiles",
  "job_name": "profiles",
  "lookback_hours": 48,
  "cutoff_datetime": "2025-11-26 10:30:00",
  "total_fetched": 5000,
  "new_profiles_found": 150,
  "profiles_with_email": 145,
  "granted": 145,
  "batches_processed": 2,
  "failed_batches": 0,
  "duration_seconds": 3.42
}
```

---

## Important Notes

### 1. Why Only NEW Profiles?
- **Efficiency**: Don't re-process thousands of existing profiles
- **Safety**: Existing profiles may have custom consent states (opted out, suppressed, etc.)
- **Performance**: Faster execution, lower API usage

### 2. Timestamp Requirements
Your profile table **must** have a timestamp field:
- `created_at` (recommended)
- `created`
- `inserted_at`

Without timestamps, profiles will be skipped.

### 3. Email vs SMS Differences

| Aspect | Email (Task 5) | SMS (Task 6) |
|--------|---------------|--------------|
| Legal requirement | T&C usually OK | **Explicit checkbox required** |
| Transactional without consent | ✅ Yes | ❌ No |
| Risk if wrong | Low | **HIGH** ($500-$1500/message) |
| Identifier | Email address | Phone (E.164 format) |

### 4. Rate Limiting
- **Batch size**: 100 profiles per request
- **Delay**: 0.5 seconds between batches
- **API limits**: 10/sec burst, 150/min sustained

---

## Troubleshooting

### Issue: "No new profiles found"
**Possible causes:**
- No profiles created within lookback window
- Timestamp field missing or named differently
- Query doesn't return timestamp column

**Solution:** 
- Check query includes timestamp field
- Verify field name matches: `created_at`, `created`, or `inserted_at`
- Try larger lookback window for testing

---

### Issue: All profiles being processed (not just new ones)
**Cause:** Timestamp field not in query results

**Solution:** Add timestamp to SELECT clause:
```sql
SELECT email, phone_number, first_name, last_name, created_at
FROM wp_klaviyo_profiles
```

---

### Issue: SMS consent fails with 400 errors
**Causes:**
- Phone format invalid (must be E.164: `+15551234567`)
- Missing SMS opt-in in Klaviyo account settings

**Solution:**
- Check temp_log.log for specific error
- Verify phone numbers have `+` and country code
- Check Klaviyo dashboard SMS settings

---

## Configuration

Both scripts read from `wp_klaviyo_globals` table:

```sql
SELECT * FROM wp_klaviyo_globals WHERE job_name = 'profiles'
```

Required fields:
- `api_key` - Klaviyo private API key
- `api_version` - API revision (e.g., '2025-10-15')
- `query` - SQL to fetch profiles (must include timestamp!)

---

## Logs

Check execution logs:
```
/wp-content/wp-custom-scripts/temp_log.log
```

Sample log output:
```
[10:30:00] GRANT EMAIL CONSENT (NEW PROFILES) - Job: profiles
[10:30:00] Lookback window: 48 hours
[10:30:00] Cutoff datetime: 2025-11-26 10:30:00
[10:30:01] Fetched 5000 total profiles
[10:30:01] Found 150 NEW profiles within lookback window
[10:30:01] 145 new profiles have valid email addresses
[10:30:01] Processing 2 batch(es) of up to 100 profiles each
[10:30:02] ✓ Batch 1 submitted successfully (Job ID: abc123)
[10:30:03] ✓ Batch 2 submitted successfully (Job ID: def456)
[10:30:03] --- EMAIL CONSENT GRANT COMPLETE ---
```

---

## SMS Legal Compliance

### ⚠️ Critical Requirements for Task 6

**DO NOT run Task 6 unless:**
1. ✅ Website has separate SMS opt-in checkbox
2. ✅ Checkbox says "I consent to receive SMS messages"
3. ✅ Checkbox is NOT pre-checked
4. ✅ User must actively check it

**T&C text mentioning SMS is NOT sufficient.**

**Penalties:**
- $500-$1,500 per unauthorized message (TCPA)
- Carrier blacklisting
- Account suspension

**If unsure:** Skip Task 6 and use email for all messages.

---

## Complete Workflow Example

### Daily New Profile Consent Grant

```bash
# Run daily at 2 AM via cron

# 1. Grant email consent for profiles created in last 24 hours
POST /wp-json/nce-runner/v1/run-task
{
  "task": 5,
  "job_name": "profiles",
  "lookback_hours": 24
}

# 2. Grant SMS consent (only if explicit opt-in exists)
POST /wp-json/nce-runner/v1/run-task
{
  "task": 6,
  "job_name": "profiles",
  "lookback_hours": 24
}
```

Result: New signups from previous day get consent granted automatically.

---

## Summary

✅ **Targets NEW profiles only** (via lookback window)  
✅ **Configurable timeframe** (default 48 hours)  
✅ **Efficient** (doesn't reprocess existing profiles)  
✅ **Safe** (preserves existing consent states)  
✅ **Fast** (only processes recent signups)  
✅ **Flexible** (works with daily, hourly, or on-demand runs)

---

## See Also

- **Task 3**: `bulk_upsert_profiles.php` - Creates/updates profile data
- **Context**: `/context/RAG_Chunk_C_Subscribe.md` - Subscribe API pattern
- **Context**: `/context/Klaviyo Transactional vs Marketing Consent SMS & Email (Summary).md` - SMS vs Email rules

