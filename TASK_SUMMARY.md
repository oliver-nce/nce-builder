# NCE Klaviyo Tasks Summary

Complete reference for all available tasks in the NCE Klaviyo integration.

---

## Task 3: Bulk Upsert Klaviyo Profiles

**Purpose:** Create or update profile attributes in Klaviyo from database query

**URL:**
```
https://your-site.com/wp-json/nce/v1/run?task=3
```

**What it does:**
- Fetches customer data from configured SQL query
- Creates/updates profiles in Klaviyo (attributes only)
- Does NOT set consent or subscription status
- Processes ~17K records in 2-3 minutes

**When to use:** Sync customer attributes from your database to Klaviyo

**Documentation:** `wp-content/wp-custom-scripts/nce-runner-task-3/`

---

## Task 4: Grant Email Marketing Consent (NEW Profiles)

**Purpose:** Grant email marketing consent to recently created profiles

**URL:**
```
https://your-site.com/wp-json/nce/v1/run?task=4
https://your-site.com/wp-json/nce/v1/run?task=4&lookback_hours=24
```

**What it does:**
- Finds profiles created within lookback window (default: 2 hours)
- Grants email marketing consent using bulk subscription endpoint
- Uses profile creation time as consent timestamp

**When to use:** After Task 3 to grant consent for new signups

**Parameters:**
- `lookback_hours` (default: 2): How far back to look for new profiles

**Documentation:** `wp-content/wp-custom-scripts/nce-runner-task-4/`

---

## Task 5: Grant SMS Marketing Consent (NEW Profiles)

**Purpose:** Grant SMS marketing consent to recently created profiles

**URL:**
```
https://your-site.com/wp-json/nce/v1/run?task=5
https://your-site.com/wp-json/nce/v1/run?task=5&lookback_hours=24
```

**What it does:**
- Finds profiles created within lookback window (default: 2 hours)
- Grants SMS marketing consent using bulk subscription endpoint
- Uses profile creation time as consent timestamp

**⚠️ LEGAL WARNING:** Only run for profiles with EXPLICIT SMS opt-in

**When to use:** After Task 3 to grant SMS consent for new signups with verified opt-in

**Parameters:**
- `lookback_hours` (default: 2): How far back to look for new profiles

**Documentation:** `wp-content/wp-custom-scripts/nce-runner-task-5/`

---

## Task 6: Bulk Unsubscribe Emails (Suppression List)

**Purpose:** Unsubscribe emails from marketing (downgrade from suppressed to unsubscribed)

**URL:**
```
https://your-site.com/wp-json/nce/v1/run?task=6&dry_run=true
https://your-site.com/wp-json/nce/v1/run?task=6
```

**What it does:**
- Fetches suppressed emails from Klaviyo API
- Unsubscribes them from marketing (removes hard suppression)
- Allows transactional emails to be sent again

**When to use:** When a suppression list was uploaded by mistake

**Parameters:**
- `dry_run` (default: false): If true, only counts emails without unsubscribing

**Documentation:** `wp-content/wp-custom-scripts/nce-runner-task-6/`

---

## Task 7: Backfill SMS Consent (ALL Profiles)

**Purpose:** ONE-TIME backfill to grant SMS consent to ALL profiles with phone numbers

**URL:**
```
https://your-site.com/wp-json/nce/v1/run?task=7&dry_run=true
https://your-site.com/wp-json/nce/v1/run?task=7
https://your-site.com/wp-json/nce/v1/run?task=7&max_profiles=2500
```

**What it does:**
- Finds ALL profiles with phone numbers but no SMS marketing consent
- Grants SMS consent in chunks (default: 1000 per run)
- Can be run multiple times for large datasets

**⚠️ LEGAL WARNING:** Only run if ALL users with phones have explicit SMS opt-in

**When to use:**
- One-time backfill of existing profiles
- After data migration
- Correcting consent status for existing users

**Parameters:**
- `dry_run` (default: false): If true, only counts profiles without granting consent
- `max_profiles` (default: 1000, max: 5000): Profiles to process per run

**Documentation:** `wp-content/wp-custom-scripts/nce-runner-task-7/`

---

## Task Workflow Examples

### Standard New Signup Flow

```bash
# 1. Sync customer attributes from database
curl "https://your-site.com/wp-json/nce/v1/run?task=3"

# 2. Grant email consent for profiles created in last 2 hours
curl "https://your-site.com/wp-json/nce/v1/run?task=4"

# 3. Grant SMS consent for profiles created in last 2 hours (if verified opt-in)
curl "https://your-site.com/wp-json/nce/v1/run?task=5"
```

### One-Time SMS Consent Backfill

```bash
# 1. Test first (dry run)
curl "https://your-site.com/wp-json/nce/v1/run?task=7&dry_run=true"

# 2. Process first 1000 profiles
curl "https://your-site.com/wp-json/nce/v1/run?task=7"

# 3. Keep running until complete
curl "https://your-site.com/wp-json/nce/v1/run?task=7"

# 4. Verify completion
curl "https://your-site.com/wp-json/nce/v1/run?task=7&dry_run=true"
```

### Fix Mistaken Suppression Upload

```bash
# 1. Check how many emails are suppressed
curl "https://your-site.com/wp-json/nce/v1/run?task=6&dry_run=true"

# 2. Downgrade to unsubscribed (allows transactional)
curl "https://your-site.com/wp-json/nce/v1/run?task=6"
```

---

## Task Comparison

| Task | Scope | Purpose | Frequency |
|------|-------|---------|-----------|
| **3** | Query results | Sync attributes | Regular (daily/hourly) |
| **4** | NEW profiles (hours) | Email consent | After each Task 3 |
| **5** | NEW profiles (hours) | SMS consent | After each Task 3 |
| **6** | Suppressed list | Fix mistakes | As needed |
| **7** | ALL profiles | SMS backfill | One-time only |

---

## Safety Features

All tasks include:
- ✅ Execution time limit (30 minutes)
- ✅ Memory limit (512MB)
- ✅ Rate limiting (respects Klaviyo limits)
- ✅ Detailed logging (`temp_log.log`)
- ✅ Error handling with retry logic
- ✅ Dry run modes (Tasks 6, 7)
- ✅ Version timestamps (verify deployed code)

---

## Response Format

All tasks return JSON with standardized fields:

```json
{
  "success": true,
  "message": "Task completed successfully",
  "task": 4,
  "job_name": "default",
  "duration_seconds": 12.5,
  "timestamp": "2025-11-28 16:45:00"
}
```

Error response:
```json
{
  "error": "Error description",
  "task": 4,
  "duration_seconds": 0.5,
  "timestamp": "2025-11-28 16:45:00"
}
```

---

## File Locations

```
wp-content/wp-custom-scripts/
├── nce-runner_task_manager.php          # Central dispatcher
├── temp_log.log                         # Execution logs
├── nce-runner-task-3/                   # Bulk upsert profiles
│   └── bulk_upsert_profiles.php
├── nce-runner-task-4/                   # Email consent (NEW)
│   ├── grant_email_consent.php
│   └── README.md
├── nce-runner-task-5/                   # SMS consent (NEW)
│   ├── grant_sms_consent.php
│   └── README.md
├── nce-runner-task-6/                   # Bulk unsubscribe
│   ├── bulk_unsubscribe_emails.php
│   └── README.md
└── nce-runner-task-7/                   # SMS backfill (ALL)
    ├── backfill_sms_consent.php
    ├── README.md
    └── QUICK_START.md
```

---

## Version Control

All PHP files include a timestamp header on line 2:

```php
// LAST UPDATED: 2025-11-28 16:45:00
```

**Before uploading to server:** Note the local file's timestamp  
**After uploading to server:** Verify the server file's line 2 matches

See `VERSION_CONTROL_RULES.md` for complete guidelines.

---

## Support & Troubleshooting

### Common Issues

**502 Bad Gateway:** Script timeout - reduce `max_profiles` or `lookback_hours`

**404 Not Found:** Wrong URL - use `/wp-json/nce/v1/run` not `/wp-json/nce-runner/v1/run`

**Old code running:** Check line 2 timestamp on server vs local file

**High error counts:** Check `temp_log.log` for Klaviyo API error details

### Logs

Detailed execution logs: `wp-content/wp-custom-scripts/temp_log.log`

### Documentation

- `PROJECT_HANDOFF.md` - Architecture and operations
- `VERSION_CONTROL_RULES.md` - Deployment verification
- `KLAVIYO_SUPPORT_MEMO.md` - Consent rules and decision logic

---

*Last updated: 2025-11-28 16:45:00*

