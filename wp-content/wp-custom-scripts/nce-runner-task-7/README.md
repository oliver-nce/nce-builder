# Task 7: Backfill SMS Marketing Consent

## Purpose

**ONE-TIME BACKFILL OPERATION:** Grant SMS marketing consent to ALL existing profiles that have phone numbers.

Unlike Task 5 (which only processes NEW profiles with a lookback window), Task 7 processes ALL profiles in your Klaviyo account.

---

## ⚠️ CRITICAL LEGAL WARNING

**DO NOT RUN THIS TASK unless:**
1. ✅ **ALL** users with phone numbers in your system have provided **explicit SMS opt-in consent**
2. ✅ You have documented proof of consent (signup forms, checkboxes, etc.)
3. ✅ Your legal team has approved this bulk operation
4. ✅ You comply with TCPA, CTIA, and all applicable SMS regulations

**This is a BULK operation that affects ALL profiles with phone numbers.**

SMS consent requirements are STRICT:
- ❌ T&C acceptance alone is NOT sufficient
- ❌ Email opt-in does NOT imply SMS consent
- ✅ Explicit SMS checkbox or keyword opt-in is REQUIRED

---

## How It Works

1. **Fetches ALL profiles** from Klaviyo API at once (no limit)
2. **Filters for valid US phone numbers** only (`+1XXXXXXXXXX` format - 11 digits)
3. **Batches the writes** to Klaviyo in groups of 100
4. **Grants SMS consent** using bulk subscription endpoint
5. **Creates new consent events** (allows re-subscribing previously unsubscribed profiles)
6. **Single run processes everything** - no need to run multiple times

✅ **US Phone Validation:** Only processes phones matching `+1XXXXXXXXXX` pattern  
✅ **No infinite loops:** Fetches all profiles once, then batches writes  
✅ **Handles re-subscriptions:** Profiles that previously unsubscribed can be re-subscribed

---

## Usage

### Dry Run (Test First!)

**ALWAYS test first** to see how many profiles will be affected:

```bash
curl "https://your-site.com/wp-json/nce/v1/run?task=7&dry_run=true"
```

Response:
```json
{
  "success": true,
  "message": "DRY RUN: Found profiles without SMS consent (no changes made)",
  "job_name": "default",
  "max_profiles": 1000,
  "dry_run": true,
  "total_found": 850,
  "with_valid_phone": 850,
  "hit_limit": false,
  "sample_phones": ["+15551234567", "+15559876543", ...],
  "duration_seconds": 5.2
}
```

---

### Live Run (Grant Consent)

Once you've verified the dry run results:

```bash
curl "https://your-site.com/wp-json/nce/v1/run?task=7"
```

**Process 1000 profiles (default):**
```bash
curl "https://your-site.com/wp-json/nce/v1/run?task=7"
```

**Process 500 profiles:**
```bash
curl "https://your-site.com/wp-json/nce/v1/run?task=7&max_profiles=500"
```

**Process 5000 profiles (maximum):**
```bash
curl "https://your-site.com/wp-json/nce/v1/run?task=7&max_profiles=5000"
```

---

### Response

```json
{
  "success": true,
  "message": "SMS consent backfill completed",
  "job_name": "default",
  "max_profiles": 1000,
  "dry_run": false,
  "total_found": 1000,
  "profiles_with_phone": 1000,
  "granted": 1000,
  "batches_processed": 10,
  "failed_batches": 0,
  "hit_limit": true,
  "next_action": "Run again to process more profiles",
  "duration_seconds": 45.8
}
```

---

## Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `max_profiles` | int | 1000 | Maximum profiles to process per run (1-5000) |
| `dry_run` | bool | false | If true, only counts profiles without granting consent |
| `job_name` | string | 'default' | Job name in `wp_klaviyo_globals` (for API key/version) |

---

## Workflow for Large Backfills

If you have **thousands of profiles** without SMS consent:

### Step 1: Dry Run
```bash
curl "https://your-site.com/wp-json/nce/v1/run?task=7&dry_run=true&max_profiles=5000"
```

Check the `total_found` and `hit_limit` fields.

### Step 2: Run in Chunks
```bash
# Process first 1000
curl "https://your-site.com/wp-json/nce/v1/run?task=7&max_profiles=1000"

# Wait 1 minute, then process next 1000
sleep 60
curl "https://your-site.com/wp-json/nce/v1/run?task=7&max_profiles=1000"

# Repeat until hit_limit = false
```

### Step 3: Verify
After all runs complete, run dry run again to confirm:
```bash
curl "https://your-site.com/wp-json/nce/v1/run?task=7&dry_run=true"
```

If `total_found: 0`, backfill is complete!

---

## Safety Limits

- **Max profiles per run:** 5000 (hard limit)
- **Max pages fetched:** 50 (5000 profiles at 100/page)
- **Batch size:** 100 profiles per API request (Klaviyo recommendation)
- **Rate limiting:** 0.5s between batches, 0.2s between pages
- **Timeout:** 30 minutes max execution time

---

## When to Use This vs Task 5

| Scenario | Use Task |
|----------|----------|
| NEW signups (last 2 hours) | **Task 5** (automatic, runs after bulk upsert) |
| NEW signups (last 24-48 hours) | **Task 5** with `lookback_hours` parameter |
| Backfill ALL existing profiles | **Task 7** (one-time operation) |
| Re-grant consent after data migration | **Task 7** (one-time operation) |

---

## Technical Details

### Klaviyo API Endpoints Used

1. **GET /api/profiles** (no filter - not supported)
   - Fetches all profiles with fields: `phone_number,created`
   - Client-side filtering for profiles with populated phone_number
   - Stops after collecting max_profiles matches

2. **POST /api/profile-subscription-bulk-create-jobs**
   - Grants SMS marketing consent in batches of 100
   - Uses `historical_import: true` to skip confirmation SMS
   - Sets `consented_at` to profile creation time (or current time)
   - Klaviyo handles duplicate consent grants gracefully (idempotent)

### Consent Timestamp

The script uses the profile's **creation timestamp** from Klaviyo as the `consented_at` value. If not available, it uses the current time.

This is required by Klaviyo when using `historical_import: true`.

---

## Troubleshooting

### "Hit max_profiles limit"
**Meaning:** There are more profiles to process.  
**Solution:** Run the task again. It will process the next chunk.

### "502 Bad Gateway"
**Meaning:** Script timed out or server overloaded.  
**Solution:** Reduce `max_profiles` to 500 or less and try again.

### "Failed batches" > 0
**Meaning:** Some batches failed to submit to Klaviyo.  
**Solution:** Check `temp_log.log` for detailed error messages. May need to re-run.

### Phone validation errors
**Meaning:** Some phone numbers are invalid format.  
**Solution:** The script filters out obviously invalid phones automatically. Invalid phones are skipped.

---

## Logs

Detailed execution logs: `wp-content/wp-custom-scripts/temp_log.log`

Shows:
- Profiles fetched per page
- Sample phone numbers
- Batch submission results
- Klaviyo API responses
- Error details

---

## File Version

Check line 2 of the file for the last update timestamp:

```php
// LAST UPDATED: 2025-11-28 16:45:00
```

Compare this to your server file after uploading to ensure you have the latest version.

---

*Last updated: 2025-11-28 16:45:00*

