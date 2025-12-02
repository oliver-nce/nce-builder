# Implementation Summary: NEW Profiles Consent Grant

## What Was Implemented

Two scripts that grant marketing consent **only to recently created profiles**, using a configurable lookback window.

---

## Files Created

### 1. `grant_email_consent.php` (Task 5)
**Purpose:** Grant email marketing consent to NEW profiles only

**Key Features:**
- ✅ Lookback window parameter (default: 48 hours)
- ✅ Filters to profiles created within window
- ✅ Checks for `created_at`, `created`, or `inserted_at` fields
- ✅ Validates email addresses
- ✅ Batches 100 profiles per request
- ✅ Detailed logging with profile counts

**Parameters:**
```json
{
  "task": 5,
  "job_name": "profiles",
  "lookback_hours": 48
}
```

---

### 2. `grant_sms_consent.php` (Task 6)
**Purpose:** Grant SMS marketing consent to NEW profiles only

**Key Features:**
- ✅ Lookback window parameter (default: 48 hours)
- ✅ Filters to profiles created within window
- ✅ Checks for `created_at`, `created`, or `inserted_at` fields
- ✅ Validates phone numbers (E.164 format)
- ✅ Batches 100 profiles per request
- ✅ Strong legal warnings for SMS compliance

**Parameters:**
```json
{
  "task": 6,
  "job_name": "profiles",
  "lookback_hours": 48
}
```

---

## Why "NEW Profiles Only"?

### Benefits
1. **Efficiency**: Don't reprocess thousands of existing profiles
2. **Safety**: Existing profiles may have custom states (opted out, suppressed)
3. **Performance**: Faster execution, lower API usage
4. **Accuracy**: Only targets profiles that need consent granted
5. **Flexibility**: Can run daily/hourly without duplication

### Assumptions
- Existing profiles already have correct consent settings
- Only new signups need consent processing
- Profile table has creation timestamps

---

## Lookback Window Logic

### How It Works
```
Current Time: 2025-11-28 10:00:00
Lookback Hours: 48
Cutoff Time: 2025-11-26 10:00:00

Processes: Profiles where created_at >= cutoff time
Skips: Profiles where created_at < cutoff time
```

### Common Use Cases

| Lookback | Use Case | Example |
|----------|----------|---------|
| 1 hour | Real-time processing | After each signup |
| 24 hours | Daily batch | Nightly cron job |
| 48 hours | Safe buffer | Default with margin |
| 168 hours | Weekly batch | Weekly processing |
| 720 hours | One-time backfill | Initial historical run |

---

## Technical Implementation

### Timestamp Detection
Scripts check for these fields (in order):
1. `created_at` (preferred)
2. `created`
3. `inserted_at`

If none found, profile is skipped.

### Filtering Process
```
1. Fetch all profiles from query
   ↓
2. Filter to: created_at >= cutoff_datetime
   ↓
3. Filter to: valid email/phone
   ↓
4. Batch into groups of 100
   ↓
5. Send to Klaviyo API
```

### Response Data
```json
{
  "lookback_hours": 48,
  "cutoff_datetime": "2025-11-26 10:00:00",
  "total_fetched": 5000,          // All profiles from query
  "new_profiles_found": 150,      // Created within window
  "profiles_with_email": 145,     // With valid identifier
  "granted": 145,                 // Successfully processed
  "batches_processed": 2,
  "failed_batches": 0,
  "duration_seconds": 3.42
}
```

---

## Key Differences from Original Version

### Original (Task 3 versions - REMOVED)
- ❌ Processed ALL profiles every run
- ❌ No time filtering
- ❌ Inefficient for ongoing use
- ❌ Could overwrite custom consent states

### New (Task 4 versions - CURRENT)
- ✅ Processes only NEW profiles
- ✅ Configurable lookback window
- ✅ Efficient for daily/hourly runs
- ✅ Preserves existing consent states
- ✅ Better logging with profile counts

---

## Configuration Requirements

### Database Table Structure
Your profile table must include a timestamp:

```sql
CREATE TABLE wp_klaviyo_profiles (
  id INT PRIMARY KEY,
  email VARCHAR(255),
  phone_number VARCHAR(20),
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  created_at DATETIME,  -- REQUIRED for lookback
  ...
);
```

### Query Configuration
Must select the timestamp column:

```sql
SELECT 
  email, 
  phone_number, 
  first_name, 
  last_name,
  created_at          -- REQUIRED
FROM wp_klaviyo_profiles
```

Store in `wp_klaviyo_globals` table under `query` column.

---

## Task Manager Registration

Updated `nce-runner_task_manager.php`:

```php
5 => [
    'folder'   => 'nce-runner-task-4',
    'file'     => 'grant_email_consent.php',
    'function' => 'nce_task_grant_email_consent',
    'description' => 'Grant email consent for NEW profiles (lookback window)'
],
6 => [
    'folder'   => 'nce-runner-task-4',
    'file'     => 'grant_sms_consent.php',
    'function' => 'nce_task_grant_sms_consent',
    'description' => 'Grant SMS consent for NEW profiles (requires explicit opt-in)'
],
```

---

## Typical Workflows

### Workflow 1: After Bulk Upsert
```bash
# 1. Upsert new profiles (Task 3)
POST {"task": 3, "job_name": "profiles"}

# Wait for completion

# 2. Grant email consent (same timeframe)
POST {"task": 5, "job_name": "profiles", "lookback_hours": 48}

# 3. Grant SMS consent (if opt-in exists)
POST {"task": 6, "job_name": "profiles", "lookback_hours": 48}
```

### Workflow 2: Daily Scheduled Job
```bash
# Run daily at 2 AM via cron

# Process profiles created in last 24 hours
POST {"task": 5, "job_name": "profiles", "lookback_hours": 24}
POST {"task": 6, "job_name": "profiles", "lookback_hours": 24}
```

### Workflow 3: Real-Time Processing
```bash
# Trigger after each signup

# Process profiles created in last hour
POST {"task": 5, "job_name": "profiles", "lookback_hours": 1}
POST {"task": 6, "job_name": "profiles", "lookback_hours": 1}
```

---

## SMS Legal Compliance

### ⚠️ Critical Requirements

**Task 6 should ONLY run if:**
- ✅ Website has separate SMS opt-in checkbox
- ✅ Checkbox clearly states: "I consent to receive SMS messages"
- ✅ Checkbox is NOT pre-checked
- ✅ User actively checks it

**T&C text alone is NOT sufficient.**

### Penalties for Non-Compliance
- $500-$1,500 per message (TCPA)
- Carrier suppression/blacklisting
- Klaviyo account suspension
- Legal liability

### Safe Alternative
If no explicit SMS opt-in:
- ✅ Run Task 5 only (email consent)
- ✅ Use email for transactional messages
- ❌ Skip Task 6 entirely

---

## Monitoring & Logs

### Log Location
```
/wp-content/wp-custom-scripts/temp_log.log
```

### What's Logged
- Lookback window and cutoff datetime
- Total profiles fetched
- NEW profiles found (within window)
- Profiles with valid identifiers
- Batch processing progress
- Klaviyo job IDs
- Errors with full details

### Sample Log
```
[10:00:00] GRANT EMAIL CONSENT (NEW PROFILES) - Job: profiles
[10:00:00] Lookback window: 48 hours
[10:00:00] Cutoff datetime: 2025-11-26 10:00:00
[10:00:01] Fetched 5000 total profiles
[10:00:01] Found 150 NEW profiles within lookback window
[10:00:01] 145 new profiles have valid email addresses
[10:00:01] Processing 2 batch(es) of up to 100 profiles each
[10:00:02] ✓ Batch 1 submitted successfully (Job ID: abc123)
[10:00:03] ✓ Batch 2 submitted successfully (Job ID: def456)
[10:00:03] Consent granted: 145
```

---

## Error Handling

### Common Issues

**"No new profiles found"**
- No profiles created within lookback window
- Timestamp field missing/not in query
- Field named differently

**"All profiles being processed"**
- Timestamp not in SELECT clause
- Timestamp field is NULL

**"HTTP 400 errors"**
- Email/phone format invalid
- Missing required fields
- API configuration issue

### Solutions
1. Verify timestamp field exists: `SELECT created_at FROM profiles LIMIT 1`
2. Add timestamp to query if missing
3. Check field name matches: `created_at`, `created`, or `inserted_at`
4. Test with small lookback first: `lookback_hours: 1`
5. Check temp_log.log for detailed error messages

---

## File Structure

```
wp-content/wp-custom-scripts/
├── nce-runner_task_manager.php  (✏️ Updated)
├── nce-runner-task-3/
│   └── bulk_upsert_profiles.php (Existing - Task 3)
└── nce-runner-task-4/           (NEW FOLDER)
    ├── grant_email_consent.php  (NEW - Task 5)
    ├── grant_sms_consent.php    (NEW - Task 6)
    ├── README.md                (Full documentation)
    ├── QUICK_START.md           (Quick reference)
    └── IMPLEMENTATION_SUMMARY.md (This file)
```

---

## Testing Strategy

### Phase 1: Small Test
```bash
# Test with 1-hour lookback
POST {"task": 5, "lookback_hours": 1}
```
- Verify only recent profiles processed
- Check logs show correct cutoff time
- Confirm profile counts make sense

### Phase 2: Daily Simulation
```bash
# Test with 24-hour lookback
POST {"task": 5, "lookback_hours": 24}
```
- Verify yesterday's signups processed
- Check batch counts
- Verify Klaviyo job IDs returned

### Phase 3: Production
```bash
# Use default 48-hour window
POST {"task": 5, "lookback_hours": 48}
POST {"task": 6, "lookback_hours": 48}  # Only if SMS opt-in exists
```

---

## Performance Characteristics

### Task 5 (Email)
- **Batch size**: 100 profiles
- **API calls**: ceil(new_profiles_with_email / 100)
- **Delay**: 0.5s between batches
- **Time estimate**: ~0.5s per batch + API latency

### Task 6 (SMS)
- **Batch size**: 100 profiles
- **API calls**: ceil(new_profiles_with_phone / 100)
- **Delay**: 0.5s between batches
- **Time estimate**: ~0.5s per batch + API latency

### Example
150 new profiles = 2 batches = ~2 seconds total

---

## Summary

✅ **Created**: 2 new lookback-based consent scripts  
✅ **Registered**: Tasks 5 & 6 in task manager (task-4 folder)  
✅ **Documented**: Full README + Quick Start  
✅ **Efficient**: Only processes NEW profiles  
✅ **Flexible**: Configurable lookback window  
✅ **Safe**: Preserves existing consent states  
✅ **Compliant**: Strong SMS legal warnings  
✅ **Production Ready**: Error handling, logging, validation  

**Default lookback: 48 hours**  
**Timestamp required: created_at, created, or inserted_at**  
**SMS requires: Explicit checkbox opt-in**

