# Quick Start: Grant Consent for NEW Profiles

## One-Line Summary
Grant marketing consent only to profiles created in the last 48 hours (or custom window).

---

## Basic Usage

### Email Consent (Task 5)
```bash
POST /wp-json/nce-runner/v1/run-task
{
  "task": 5,
  "job_name": "profiles",
  "lookback_hours": 48
}
```

### SMS Consent (Task 6)
⚠️ Only if explicit SMS opt-in exists

```bash
POST /wp-json/nce-runner/v1/run-task
{
  "task": 6,
  "job_name": "profiles",
  "lookback_hours": 48
}
```

---

## Parameters

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `task` | Yes | - | 5 = email, 6 = SMS |
| `job_name` | No | 'profiles' | Config name in wp_klaviyo_globals |
| `lookback_hours` | No | 48 | How far back to look for new profiles |

---

## Common Lookback Values

- **1 hour**: Real-time after signup
- **24 hours**: Daily batch (most common)
- **48 hours**: Default with buffer for delays
- **168 hours**: Weekly batch
- **720 hours**: One-time 30-day backfill

---

## What Gets Processed?

### Task 5 (Email)
1. Fetches all profiles from query
2. Filters to created_at >= (now - lookback_hours)
3. Filters to valid email addresses only
4. Grants email marketing consent
5. Returns count of processed profiles

### Task 6 (SMS)
1. Fetches all profiles from query
2. Filters to created_at >= (now - lookback_hours)
3. Filters to valid phone numbers only (E.164 format)
4. Grants SMS marketing consent
5. Returns count of processed profiles

---

## Requirements

### Database
Your profile table must have a timestamp column:
- `created_at` (preferred)
- `created` 
- `inserted_at`

### Query
Must select the timestamp column:
```sql
SELECT email, phone_number, created_at
FROM wp_klaviyo_profiles
```

### SMS (Task 6 only)
- ✅ Explicit SMS opt-in checkbox on website
- ✅ Clear consent language
- ✅ NOT pre-checked
- ❌ T&C text alone is NOT sufficient

---

## Typical Workflow

### After Bulk Upsert (Task 3)
```bash
# 1. Upsert profiles
POST {"task": 3, "job_name": "profiles"}

# 2. Grant email consent (same lookback as upsert window)
POST {"task": 5, "job_name": "profiles", "lookback_hours": 48}

# 3. Grant SMS consent (if explicit opt-in exists)
POST {"task": 6, "job_name": "profiles", "lookback_hours": 48}
```

### Daily Scheduled Run
```bash
# Run at 2 AM daily
POST {"task": 5, "job_name": "profiles", "lookback_hours": 24}
POST {"task": 6, "job_name": "profiles", "lookback_hours": 24}
```

---

## Response Example

```json
{
  "success": true,
  "message": "Email consent grant completed for new profiles",
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

## Logs

Check `/wp-content/wp-custom-scripts/temp_log.log` for:
- Cutoff datetime
- Profiles found vs processed
- Batch progress
- Any errors

---

## Troubleshooting

**"No new profiles found"**
→ Check timestamp field exists and is in query

**"All profiles being processed"**
→ Timestamp field not in SELECT clause

**SMS fails with 400**
→ Phone format must be E.164: `+15551234567`

---

## Safety Notes

✅ **Only processes NEW profiles** (old ones untouched)  
✅ **Preserves existing consent states**  
✅ **Won't modify profiles that opted out**  
✅ **Efficient** (doesn't reprocess entire database)

⚠️ **SMS requires explicit checkbox** (not T&C text)  
⚠️ **$500-$1500 per message fine** if SMS consent wrong

---

## Next Steps

1. Verify timestamp field in your database
2. Update query to include timestamp if missing
3. Test with small lookback first (`lookback_hours: 1`)
4. Check logs to verify correct profiles processed
5. Scale to full lookback window
6. Set up scheduled runs if desired

